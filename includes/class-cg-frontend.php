<?php
class CG_Frontend {
    private $cache;
    private $image_sizes = [];

    public function __construct() {
        add_shortcode('category_grid', [$this, 'render_grid']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('init', [$this, 'register_image_sizes']);
        $this->cache = CG_Cache::init();
    }

    public function register_image_sizes() {
        // Register optimized sizes for different viewports
        add_image_size('cg-desktop', 480, 480, true);
        add_image_size('cg-tablet', 320, 320, true);
        add_image_size('cg-mobile', 240, 240, true);
        
        $this->image_sizes = [
            'desktop' => 'cg-desktop',
            'tablet' => 'cg-tablet',
            'mobile' => 'cg-mobile'
        ];
    }

    private function get_category_fragment_cache_key($category, $settings) {
        return 'cg_cat_' . md5(serialize($category) . serialize($settings));
    }

    private function get_responsive_image_src($attachment_id, $fallback_url) {
        if (!$attachment_id) {
            return [
                'src' => $fallback_url,
                'srcset' => '',
                'sizes' => ''
            ];
        }

        $image_meta = wp_get_attachment_metadata($attachment_id);
        if (!$image_meta) {
            return [
                'src' => $fallback_url,
                'srcset' => '',
                'sizes' => ''
            ];
        }

        $srcset = [];
        $sizes = [];

        foreach ($this->image_sizes as $device => $size) {
            $image = wp_get_attachment_image_src($attachment_id, $size);
            if ($image) {
                $srcset[] = "{$image[0]} {$image[1]}w";
                $sizes[] = "({$device}-width: {$image[1]}px) {$image[1]}px";
            }
        }

        return [
            'src' => wp_get_attachment_image_url($attachment_id, 'cg-desktop'),
            'srcset' => implode(', ', $srcset),
            'sizes' => implode(', ', $sizes)
        ];
    }

    private function render_category_item($category, $settings) {
        $cache_key = $this->get_category_fragment_cache_key($category, $settings);
        $cached_html = wp_cache_get($cache_key, 'category_grid_fragments');
        
        if ($cached_html !== false) {
            return $cached_html;
        }

        $term = get_term($category['id']);
        if (!$term) return '';
        
        $thumbnail_id = get_term_meta($category['id'], 'thumbnail_id', true);
        $fallback_url = CG_PLUGIN_URL . 'assets/images/default-category.jpg';
        
        $image_data = !empty($category['image']) ? 
            ['src' => $category['image'], 'srcset' => '', 'sizes' => ''] : 
            $this->get_responsive_image_src($thumbnail_id, $fallback_url);
        
        ob_start();
        ?>
        <div class="cg-bx">
            <div class="cg-tilethumb">
                <a href="<?php echo !empty($category['link']) ? esc_url($category['link']) : esc_url(get_term_link($term)); ?>"
                   class="cg-category-tile">
                    <img src="<?php echo esc_url($image_data['src']); ?>" 
                         <?php if ($image_data['srcset']): ?>
                         srcset="<?php echo esc_attr($image_data['srcset']); ?>"
                         sizes="<?php echo esc_attr($image_data['sizes']); ?>"
                         <?php endif; ?>
                         alt="<?php echo !empty($category['alt']) ? esc_attr($category['alt']) : esc_attr($term->name); ?>"
                         class="cg-category-image"
                         width="480"
                         height="480"
                         loading="lazy"
                         decoding="async">
                    <noscript>
                        <img src="<?php echo esc_url($image_data['src']); ?>" 
                             alt="<?php echo !empty($category['alt']) ? esc_attr($category['alt']) : esc_attr($term->name); ?>"
                             width="480"
                             height="480">
                    </noscript>
                </a>
            </div>
        </div>
        <?php
        
        $html = ob_get_clean();
        wp_cache_set($cache_key, $html, 'category_grid_fragments', HOUR_IN_SECONDS);
        
        return $html;
    }

    public function render_grid($atts) {
        $atts = shortcode_atts(['slug' => ''], $atts);
        if (empty($atts['slug'])) return '';

        $cached_output = $this->cache->get_shortcode_cache($atts['slug']);
        if ($cached_output !== false) {
            return $cached_output;
        }

        $grid = $this->cache->get_grid($atts['slug']);
        if (!$grid) return '';

        $categories = json_decode($grid->categories, true);
        $settings = json_decode($grid->settings, true);
        
        ob_start();
        ?>
        <div class="cg-wrapper">
            <div class="cg-grid-container" 
                 data-columns="<?php echo esc_attr($settings['desktop_columns']); ?>"
                 data-mobile-columns="<?php echo esc_attr($settings['mobile_columns']); ?>"
                 data-carousel="<?php echo $settings['carousel_mobile'] ? 'true' : 'false'; ?>">
                
                <div class="cg-category-heading">
                    <span>Categories</span>
                </div>

                <div class="cg-row">
                    <?php 
                    foreach ($categories as $category) {
                        echo $this->render_category_item($category, $settings);
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
        
        $output = ob_get_clean();
        $this->cache->cache_grid_output($atts['slug'], $settings, $output);
        
        return $output;
    }

    public function enqueue_assets() {
        $style_version = $this->cache->get_asset_version('frontend-css');
        $script_version = $this->cache->get_asset_version('frontend-js');
        
        wp_enqueue_style(
            'cg-frontend-css', 
            CG_PLUGIN_URL . 'assets/css/frontend.css', 
            [], 
            $style_version
        );
        
        wp_enqueue_script(
            'cg-frontend-js', 
            CG_PLUGIN_URL . 'assets/js/frontend.js', 
            ['jquery'], 
            $script_version, 
            true
        );
    }
}