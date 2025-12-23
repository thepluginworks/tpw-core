<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// This file is only loaded after Elementor is confirmed loaded.

class TPW_Elementor_Widget_Gallery extends \Elementor\Widget_Base {
    public function get_name() {
        return 'tpw_gallery';
    }

    public function get_title() {
        return __( 'TPW Gallery', 'tpw-core' );
    }

    public function get_icon() {
        // Use a core Elementor icon class name.
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        // Default Elementor category.
        return [ 'general' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'section_content',
            [
                'label' => __( 'Content', 'tpw-core' ),
            ]
        );

        $this->add_control(
            'gallery_id',
            [
                'label'       => __( 'Gallery', 'tpw-core' ),
                'type'        => \Elementor\Controls_Manager::SELECT2,
                'options'     => [],
                'multiple'    => false,
                'label_block' => true,
                'description' => __( 'Search by title to select a gallery.', 'tpw-core' ),
            ]
        );

        $this->add_control(
            'view',
            [
                'label'   => __( 'View', 'tpw-core' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid'  => __( 'Grid', 'tpw-core' ),
                    'list'  => __( 'List', 'tpw-core' ),
                    'story' => __( 'Story', 'tpw-core' ),
                ],
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'     => __( 'Columns', 'tpw-core' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => '3',
                'options'   => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
                'condition' => [
                    'view' => 'grid',
                ],
            ]
        );

        $this->add_control(
            'paginate',
            [
                'label'        => __( 'Paginate', 'tpw-core' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __( 'Yes', 'tpw-core' ),
                'label_off'    => __( 'No', 'tpw-core' ),
                'return_value' => 'yes',
                'default'      => '',
                'condition'    => [
                    'view' => [ 'grid', 'list' ],
                ],
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label'       => __( 'Per page', 'tpw-core' ),
                'type'        => \Elementor\Controls_Manager::NUMBER,
                'min'         => 1,
                'max'         => 200,
                'step'        => 1,
                'default'     => 12,
                'description' => __( 'Number of images per page when pagination is enabled.', 'tpw-core' ),
                'condition'   => [
                    'view'     => [ 'grid', 'list' ],
                    'paginate' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $gallery_id = isset( $settings['gallery_id'] ) ? (int) $settings['gallery_id'] : 0;
        if ( $gallery_id <= 0 ) {
            return;
        }

        $view = isset( $settings['view'] ) ? (string) $settings['view'] : 'grid';
        $columns = isset( $settings['columns'] ) ? (int) $settings['columns'] : 3;
        $paginate = isset( $settings['paginate'] ) && (string) $settings['paginate'] === 'yes';
        $per_page = isset( $settings['per_page'] ) ? (int) $settings['per_page'] : 0;
        if ( $per_page < 1 ) {
            $per_page = 0;
        }

        if ( function_exists( 'tpw_gallery_render' ) ) {
            $args = [
                'id'      => $gallery_id,
                'view'    => $view,
                'columns' => $columns,
            ];

            // Pagination is supported only for grid/list.
            if ( $paginate && in_array( $view, [ 'grid', 'list' ], true ) && $per_page > 0 ) {
                $args['per_page'] = $per_page;
            }

            echo tpw_gallery_render( $args );
        }
    }
}
