# TPW Core Sign-Up System

## Design Specification

Version: 0.1  
Status: Draft  
Owner: ThePluginWorks

## 1. Purpose

The TPW Core Sign-Up System defines a reusable onboarding engine for organisations using ThePluginWorks plugins. It exists to solve a common problem across subscriptions, lodges, clubs, events, and volunteer onboarding: collect structured signup data, take payment where required, and only create durable user and member records once the commercial or administrative preconditions have been met.

Today, different onboarding flows tend to mix form rendering, payment handling, account creation, and plugin-specific business rules into a single path. That makes recovery difficult when payment fails, when a customer abandons the process, or when an administrator needs to resume a partially completed signup. The new sign-up system separates lifecycle management from plugin-specific form content so that onboarding can be retried, resumed, audited, and extended consistently.

The system is designed as a generic onboarding engine for organisations using ThePluginWorks plugins. TPW Core provides the lifecycle, storage, and recovery framework. Plugins such as FlexiSubscriptions add domain-specific sections, validations, payment behaviours, and finalization actions without owning the underlying lifecycle state.

## 2. Design Principles

The architecture is based on the following principles:

- No WordPress user or TPW member record is created until payment succeeds.
- The signup lifecycle must be recoverable and resumable at every meaningful stage.
- TPW Core owns the lifecycle engine, persistence model, and status transitions.
- Plugins can extend the signup form, but they do not control lifecycle storage.
- Passwords are not collected before payment. Users set their password after successful payment.
- Signup forms must support plugin-defined sections and repeatable groups from day one.

These principles prevent premature account creation, reduce orphaned records, and ensure that the sign-up system can serve multiple product types without each plugin rebuilding the same infrastructure.

## 3. System Overview

The high-level signup flow is:

Signup form -> signup attempt created -> payment gateway called -> payment result stored -> finalization executed -> WordPress user and TPW member records created

The signup form gathers core fields, signup-safe custom member fields, plugin-defined sections, and repeatable group data. Submission creates a signup attempt record that becomes the source of truth for the transaction. Payment is then initiated using the plugin-defined flow. TPW Core stores the payment outcome and either advances to finalization or records the failure state.

If payment succeeds, finalization creates the WordPress user, creates the TPW member record, persists signup-safe field values, invokes plugin finalization callbacks, and redirects the user into the password setup process. If payment fails, the attempt remains available for retry using sanitized retry data. If finalization fails after payment succeeds, the attempt is marked for recovery so an administrator can resume the process without charging the user again. The durable outputs of finalization should be recorded primarily in `result_payload_json`, including references to created records, plugin-owned resources, and downstream result metadata.

Retries work by reusing the existing signup attempt with an updated payment action, rather than creating duplicate records for the same in-progress onboarding. Admin recovery tools allow staff to inspect failed or stalled attempts, resume finalization, mark attempts as abandoned, or expire attempts that are no longer valid.

## 4. Architecture Layers

### TPW Core Layer

TPW Core is responsible for the generic signup lifecycle and shared framework services, including:

- signup attempts
- lifecycle engine
- field registry
- section registry
- repeatable group support
- admin recovery tools
- password setup flow

This layer owns status transitions, storage, recovery rules, audit timestamps, payload hygiene, and the orchestration contract used by plugins.
This layer also owns lifecycle locking, expiry, activity tracking, retry counters, and the generic representation of payment and finalization state. Plugins do not write arbitrary status values directly to the attempt record.

### Plugin Layer

Plugins extend the framework with domain-specific behaviour, including:

- custom sections
- repeatable groups
- domain validation
- payment logic
- finalization logic

Plugins define what additional data is needed for a signup flow and what business rules must run before or after finalization. They contribute request and retry payload content, payment orchestration inputs, validation rules, and finalization callbacks. They do not own the primary signup attempt record, lifecycle status model, locking model, or recovery screens.

## 5. Signup Attempt Lifecycle

Each signup attempt moves through a defined lifecycle represented by the following statuses.

### `draft`

The attempt has been initialized but has not yet started payment processing. This is the initial state after a valid signup form submission is stored.

### `payment_pending`

The attempt has been handed off to the payment process and is awaiting a definitive result. This includes synchronous in-flight payment submission and asynchronous callback windows.

### `payment_failed`

Payment did not complete successfully. No user or member record has been created. The attempt may be retried if the plugin flow permits retry.

### `payment_succeeded`

Payment has been confirmed and the system is ready to finalize account creation and downstream plugin actions.

### `finalizing`

Core finalization has started. This status prevents duplicate processing while user creation, member creation, field persistence, and plugin callbacks are running.

### `completed`

The signup lifecycle has finished successfully. The WordPress user and TPW member records have been created, plugin finalization has completed, and the user can proceed into password setup or authenticated post-signup flows.

### `finalization_failed`

Payment succeeded, but one or more finalization steps failed. This state is recoverable through admin tools because charging the customer again is not appropriate.

### `expired`

The attempt has passed its valid recovery or retry window and should no longer be resumed. Expiration may be triggered by lifecycle rules or an administrator.

### `abandoned`

The customer did not complete the process and the attempt is treated as inactive. This status is useful for operational reporting and cleanup without implying a hard expiry.

## 6. Database Schema

The sign-up system stores lifecycle records in a dedicated table named `wp_tpw_signup_attempts`.

The purpose of this table is to hold the canonical state of every signup journey before permanent account creation occurs. It captures the current lifecycle status, flow ownership, payer identity, safe request data, retry data, payment outcome data, locking state, operational counters, and timestamps needed for auditing, retries, recovery, and expiry.

The table is intentionally generic. It should not be overfitted to a single consumer such as Members or FlexiSubscriptions. Core owns the top-level lifecycle columns. Plugins contribute payload content and finalization behaviour, while references to created records such as `wp_user_id` or `member_id` should typically be stored in `result_payload_json` rather than guaranteed as dedicated top-level columns. Core keeps lifecycle metadata in top-level columns while plugins and finalization routines record domain-specific references in the result payload.

### Logical Columns

| Column | Purpose |
| --- | --- |
| `id` | Internal primary key for the signup attempt. |
| `public_token` | Public-safe token used in links, callbacks, retries, and recovery operations. |
| `flow_key` | Identifies the owning signup flow, for example `tpw-subscriptions:join`. |
| `plugin_key` | Identifies the plugin responsible for extension logic. |
| `status` | Current lifecycle status of the attempt. |
| `email` | Primary contact email collected during signup. |
| `first_name` | Optional denormalized first name for admin usability and filtering. |
| `last_name` | Optional denormalized last name for admin usability and filtering. |
| `request_fingerprint` | Stable fingerprint of the normalized request data used for deduplication, diagnostics, and retry safety checks. |
| `gateway` | Generic payment gateway identifier selected for the flow. |
| `amount` | Expected payable amount for the attempt, stored in minor units or a defined canonical numeric format. |
| `currency_code` | ISO currency code for the attempt amount. |
| `request_payload_json` | Sanitized snapshot of signup request data needed to render, validate, and finalize the flow. |
| `retry_payload_json` | Sanitized payload used to retry payment or resume user-facing completion steps. |
| `result_payload_json` | Sanitized result data from payment and finalization outcomes. |
| `payment_provider` | Payment provider identifier used by the flow. |
| `payment_reference` | Provider-specific transaction or intent reference. |
| `payment_status` | Generic normalized payment state recorded by Core for lifecycle routing. |
| `payment_receipt_reference` | Provider receipt or settlement reference if one is returned. |
| `payment_result_code` | Machine-readable payment outcome code normalized by Core where possible. |
| `last_error_code` | Machine-readable summary of the latest failure state. |
| `last_error_message` | Human-readable summary of the latest failure state for recovery workflows. |
| `payment_attempt_count` | Number of payment attempts initiated for the signup attempt. |
| `retry_count` | Number of explicit retry operations performed for the record. |
| `finalization_attempt_count` | Number of finalization attempts performed for the record. |
| `lock_token` | Opaque token used to claim exclusive processing ownership during payment or finalization transitions. |
| `locked_at` | Timestamp when the current lock was acquired. |
| `created_at` | Timestamp when the attempt was created. |
| `updated_at` | Timestamp when the attempt was last modified. |
| `last_activity_at` | Timestamp for the latest meaningful user or system activity on the attempt. |
| `payment_started_at` | Timestamp when payment processing began. |
| `payment_completed_at` | Timestamp when payment reached a terminal result. |
| `finalization_started_at` | Timestamp when finalization began. |
| `finalization_completed_at` | Timestamp when finalization ended successfully. |
| `expires_at` | Timestamp after which the attempt should no longer be resumed. |
| `expired_at` | Timestamp when the attempt expired, if applicable. |
| `abandoned_at` | Timestamp when the attempt was marked abandoned, if applicable. |

### Proposed SQL Schema

```sql
CREATE TABLE `wp_tpw_signup_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_token` char(64) NOT NULL,
  `flow_key` varchar(100) NOT NULL,
  `plugin_key` varchar(100) NOT NULL,
  `status` varchar(40) NOT NULL,
  `email` varchar(190) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `request_fingerprint` char(64) DEFAULT NULL,
  `gateway` varchar(100) DEFAULT NULL,
  `amount` bigint unsigned DEFAULT NULL,
  `currency_code` char(3) DEFAULT NULL,
  `request_payload_json` longtext DEFAULT NULL,
  `retry_payload_json` longtext DEFAULT NULL,
  `result_payload_json` longtext DEFAULT NULL,
  `payment_provider` varchar(100) DEFAULT NULL,
  `payment_reference` varchar(190) DEFAULT NULL,
  `payment_status` varchar(40) DEFAULT NULL,
  `payment_receipt_reference` varchar(190) DEFAULT NULL,
  `payment_result_code` varchar(100) DEFAULT NULL,
  `last_error_code` varchar(100) DEFAULT NULL,
  `last_error_message` text DEFAULT NULL,
  `payment_attempt_count` int unsigned NOT NULL DEFAULT 0,
  `retry_count` int unsigned NOT NULL DEFAULT 0,
  `finalization_attempt_count` int unsigned NOT NULL DEFAULT 0,
  `lock_token` char(64) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `last_activity_at` datetime DEFAULT NULL,
  `payment_started_at` datetime DEFAULT NULL,
  `payment_completed_at` datetime DEFAULT NULL,
  `finalization_started_at` datetime DEFAULT NULL,
  `finalization_completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `abandoned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_token` (`public_token`),
  KEY `flow_key_status` (`flow_key`, `status`),
  KEY `plugin_key_status` (`plugin_key`, `status`),
  KEY `email_status` (`email`, `status`),
  KEY `request_fingerprint` (`request_fingerprint`),
  KEY `gateway_status` (`gateway`, `status`),
  KEY `payment_reference` (`payment_reference`),
  KEY `payment_provider_status` (`payment_provider`, `payment_status`),
  KEY `lock_token` (`lock_token`),
  KEY `expires_at` (`expires_at`),
  KEY `last_activity_at` (`last_activity_at`),
  KEY `created_at` (`created_at`),
  KEY `updated_at` (`updated_at`)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Indexing Strategy and Lifecycle Timestamps

Indexes support the most common operational queries: locate attempts by public token, inspect plugin-specific queues, filter recovery lists by status, search by email, trace provider transaction references, inspect expiring records, and identify stale locked attempts. The paired status indexes make admin recovery and scheduled cleanup efficient because they avoid full-table scans for open or failed attempts.

Lifecycle timestamps provide an audit trail for reporting and for time-based rules such as marking stale payment attempts as abandoned or expiring unrecoverable records after a retention window. Separate timestamps for payment and finalization phases make it possible to diagnose whether a failure happened before or after funds were confirmed. `last_activity_at` and `expires_at` support resumability and cleanup policies, while `locked_at` supports safe recovery from interrupted workers or callbacks.

Top-level columns should remain limited to generic lifecycle concerns. Created entity identifiers, downstream records, plugin resource references, and other finalization outputs should primarily be written into `result_payload_json` so the model stays generic across subscriptions, clubs, events, and future onboarding flows.

## 7. Field Registry System

The field registry is the source of truth for all fields that can appear in the signup form. TPW Core uses it to determine rendering order, validation, persistence rules, and whether a field is safe to capture before payment completes.

The registry supports three broad categories of fields:

- standard core fields
- signup-safe custom fields
- plugin-defined fields inside custom sections and repeatable groups

Standard core fields cover universal identity and contact information used by most onboarding flows and are owned directly by TPW Core. Signup-safe custom fields are configurable Members fields that have been explicitly approved for pre-payment capture and lifecycle storage. Plugin-defined fields are introduced through plugin-owned sections and repeatable groups; they are rendered through the Core registry pipeline but remain domain-specific in meaning.

This distinction is important. Core fields define the baseline onboarding contract. Signup-safe custom fields extend that contract through Members configuration without bypassing Core safety rules. Plugin-defined fields do not become generic Core fields simply because they appear in the same form; they remain attached to plugin-owned sections or groups and are interpreted by plugin validation and finalization logic.

Each field definition should include the following metadata:

- field key
- label
- type
- section
- signup enabled
- signup required
- signup safe
- validation rules
- storage target
- display order

This metadata allows Core to determine whether the field is rendered on the public signup form, which section it belongs to, whether it is mandatory during signup, how it should be validated, and where its value should be written during finalization. Storage targets may include the signup attempt payload, WordPress user data, TPW member data, plugin-owned records, or a combination of those where explicitly permitted.

Fields appear in the signup form only when signup is enabled for that field and the field is valid for the active flow. Core fields and eligible signup-safe custom fields render through the same registry pipeline. Plugin-defined fields must be attached to registered plugin sections or repeatable groups rather than injected as unstructured standalone fields.

## 8. Section System

Sections group related fields into coherent blocks within the signup form. They provide a stable rendering structure for both core and plugin-defined fields.

Example sections include:

- Account Details
- Personal Details
- Address
- Emergency Contact

Each section definition should include the following metadata:

- section key
- label
- owner
- description
- sort order
- conditional rules
- repeatable flag

The section key provides a stable identifier. The label and description support UI rendering and documentation. The owner identifies whether the section belongs to TPW Core or a plugin. Sort order controls rendering sequence. Conditional rules determine whether the section should be displayed for a given flow or based on prior user choices. The repeatable flag indicates whether the section is a normal single instance block or a repeatable container handled by the repeatable group framework.

## 9. Repeatable Groups

Repeatable groups allow structured repeating entries to be captured as part of a signup flow. They support scenarios such as adding a partner, children, or dependants during onboarding.

Typical repeatable groups include:

- partner
- children
- dependants

Each repeatable group definition should include the following metadata:

- group key
- label
- owner
- fields
- min rows
- max rows
- conditional rules
- row label pattern

The group key uniquely identifies the group within the flow. The owner indicates whether the group is registered by Core or a plugin. The fields list defines the row schema. Minimum and maximum row counts constrain the number of entries. Conditional rules determine when the group is shown. The row label pattern defines how rows are presented in the UI, for example `Child 1`, `Child 2`, or `Dependant 1`.

Plugins define repeatable groups through the same extension model used for sections and fields. Core owns the rendering, request normalization, validation pipeline, and safe payload storage so repeatable data behaves consistently across products.

## 10. Plugin Extension API

Plugins extend the signup system by registering lifecycle-safe metadata and callbacks with TPW Core. Plugins can register:

- custom sections
- repeatable groups
- additional fields
- validation rules
- finalization callbacks

Each plugin flow is identified by a flow key such as `tpw-subscriptions:join`. The flow key acts as the routing key for form composition, validation, payment orchestration, and finalization dispatch.

Core receives the active flow key, resolves all registered sections, groups, and fields for that flow, and then executes lifecycle processing using Core-owned storage and status transitions. Plugins may contribute request and retry payload fragments, but Core decides how those payloads are normalized, persisted, and advanced through lifecycle states. When finalization is reached, Core dispatches to plugin finalization callbacks associated with the flow key. This keeps plugin business rules pluggable while ensuring the lifecycle engine remains centralised and recoverable.

## 11. Payload System

Each signup attempt stores three separate payloads to support recovery and auditability.

### `request_payload_json`

This payload contains the sanitized request snapshot captured from the signup form. It should include all signup-safe field values, selected flow options, repeatable group data, and any plugin metadata needed to reproduce the intended signup state.

### `retry_payload_json`

This payload contains the subset of sanitized data needed to retry payment or resume user-facing progression without reconstructing the attempt from scratch. It should be intentionally smaller than the full request payload where possible.

### `result_payload_json`

This payload contains sanitized outcome data produced by payment processing and finalization. It may include provider references, status summaries, plugin outcome details, audit information required for support or diagnostics, and references to created records such as WordPress users, TPW members, subscription records, plan assignments, or other plugin-owned entities.

Payload storage is subject to strict security restrictions. The following data must never be stored in signup attempt payloads:

- passwords
- card tokens
- recaptcha tokens

More broadly, Core should store only the minimum data required to resume, finalize, or audit the lifecycle. Sensitive values that are single-use, secret-bearing, or not required after immediate validation should be discarded.

## 12. Password Flow

Users do not choose a password during signup.

After successful payment:

- a WordPress user is created with a temporary password
- the user is logged in
- the user is redirected to a force-set-password page

This approach improves security because password handling is removed from the pre-payment public form, reducing the risk of storing or replaying credentials in intermediate lifecycle records. It also simplifies retries and recovery because the signup attempt never needs to retain password state. The final password is chosen only after payment succeeds and the account is actually created.

## 13. Members Settings UI

Signup configuration should appear inside the Members settings area at:

Members -> Settings -> Sign-Ups

For Phase 1, this should be presented as a dedicated Sign-Up Form tab or equivalent Sign-Ups settings surface within Members settings. The intent is to give administrators a focused place to control what appears on the public signup form while still making it clear that lifecycle behaviour is owned by Core.

Phase 1 configuration options should include:

- Enable Sign-Ups
- Sign-Up Page
- Field Visibility
- Required Flags
- Plugin Extension Visibility/Summary

Field configuration should allow administrators to mark eligible core fields and signup-safe custom fields as:

- show on signup
- required on signup

The Sign-Up Form settings should also expose a plugin extension summary so administrators can see which plugins are contributing additional sections or repeatable groups to the active signup experience. In Phase 1 this can be a visibility and summary layer rather than a full plugin form builder.

This settings area gives administrators a controlled way to enable signups, select the public page used to host the signup shortcode, and manage which eligible fields participate in onboarding without handing over lifecycle ownership to plugins.

## 14. Public Signup Form Rendering

The public signup form is rendered using the shortcode `[tpw_member_signup]`.

The rendering order is:

1. Core fields
2. Signup-safe custom fields
3. Plugin sections
4. Payment section

Core fields render first to guarantee consistent identity and contact capture. Eligible signup-safe custom member fields render next so existing Members configuration is respected without weakening pre-payment data safety rules. Plugin sections then add domain-specific data. The payment section always renders last because it depends on the validated signup state and represents the transition into lifecycle processing.

Conditional visibility rules apply at both field and section level. A field or section may be hidden based on the active flow, prior answers, membership plan selection, or plugin-defined conditions. Core evaluates these rules during rendering and validation so hidden fields do not create inconsistent submission requirements.

## 15. Admin Management Screens

For Phase 1, the planned operational recovery UI is inside the Members front-end admin area at:

`[tpw_manage_members]` -> Tools -> Sign-Ups

Phase 1 screens should include:

- signup attempt list
- signup attempt detail
- resume finalization
- mark abandoned
- mark expired

The signup attempt list should support filtering by plugin, status, email, and date. The detail view should show the current status, lifecycle timestamps, safe payload summaries, provider references, retry counters, finalization counters, locking state, and the latest error information. Recovery actions should be permission-controlled and designed to avoid duplicate payment capture or duplicate member creation. This placement is the intended Phase 1 operational UI and does not preclude a different long-term admin surface later.

## 16. FlexiSubscriptions Integration

FlexiSubscriptions will use the sign-up system as a plugin consumer rather than implementing its own lifecycle engine. It can define domain-specific onboarding inputs such as:

- membership plan selection
- family membership
- partner section
- children repeatable group

For example, a subscription join flow may require a plan selector, collect a partner's personal details for a family membership, and allow multiple child rows through a repeatable group. FlexiSubscriptions defines those sections, validations, and finalization hooks, while TPW Core remains responsible for creating and managing the signup attempt, tracking payment outcome, handling retries, and completing account creation.

## 17. Phase 1 Scope

Phase 1 will implement the minimum architecture required to establish the sign-up framework inside TPW Core and support a first production integration with FlexiSubscriptions. Plugin-defined sections and repeatable groups are a day-one requirement in this phase, not a later enhancement, because the generic lifecycle engine is only useful if plugins can extend the form structure from the start.

Included in Phase 1:

- Core lifecycle engine
- signup attempts table
- field registry integration
- plugin-defined sections from day one
- repeatable group support from day one
- plugin extension support
- retry and recovery
- force-set-password flow
- FlexiSubscriptions integration

## 18. Out of Scope

The following items are explicitly out of scope for Phase 1:

- visual form builder
- generic payment gateway abstraction
- advanced conditional logic editor
- plugin UI SDK
- full backend admin interface

These items may be considered later, but they are not required to establish the core lifecycle, storage, and extension framework.