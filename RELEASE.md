# TPW Core Plugin – Release Checklist

Use this guide whenever you're releasing a new version to GitHub.

---

## ✅ Step-by-Step Git Commands

### 1. Check Git status
```bash
git status
```

### 2. Stage all changes
```bash
git add .
```

### 3. Commit with a message
```bash
git commit -m "Describe what changed"
```

### 4. Tag the version (match the plugin header version)
```bash
git tag v1.2.0
```

> Update the tag version accordingly.

### 5. Push everything to GitHub
```bash
git push origin main --tags
```

This will trigger GitHub Actions to build the release zip, attach it to the GitHub release, and publish the update manifest.

---

## 💡 Optional: Bash Alias

Add this to `.bash_profile` or `.zshrc`:
```bash
alias tpw-release='git add . && git commit -m "Release update" && git tag v$(grep "Version:" tpw-core.php | awk "{ print \$3 }") && git push origin main --tags'
```

Then use:
```bash
tpw-release
```
