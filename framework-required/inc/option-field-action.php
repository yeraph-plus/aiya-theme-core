<?php
if (!defined('ABSPATH')) exit;

/**
 * AIYA-CMS Theme Options Framework 组件方法
 * 
 * @version 1.0
 */

if (!class_exists('AYA_Field_Action')) exit;

abstract class AYA_Field_Action
{
    public $class_name = 'AYA_Option_Fired_';

    //组件方法
    public function field($field)
    {
        //过滤组件
        if (in_array($field['type'], array('content', 'title_h1', 'title_h2', 'title_h3'))) {
            return self::field_title($field);
        }
        //检查回调
        if (in_array($field['type'], array('callback'))) {
            $callback_class = $this->class_name . $field['type'];
            if (class_exists($callback_class)) {
                //New
                $callback = new $callback_class($field);
                return $callback->callback($field);
            }
        }
        //检查默认值
        if (empty($field['default'])) {
            $field['default'] = '';
        }
        //调用组件
        if (!empty($field['id']) && isset($field['id'])) {

            $new_class = $this->class_name . $field['type'];

            //开始调用
            if (class_exists($new_class)) {
                //New
                $class = new $new_class();

                $class->action($field);
            } else {
                //报错
                echo '<p class="error">' . esc_html__('Field not found "type" : ') . $field['type'] . '</p>';
            }
        } else {
            //报错
            echo '<p class="error">' . esc_html__('Field not found "id" : ') . $field['id'] . '</p>';
        }
    }
    //输出标题
    public function field_title($field)
    {
        echo '<div class="form-field none-border">';

        if (!empty($field['desc'])) {
            switch ($field['type']) {
                case 'title_h1':
                    echo '<h1>' . $field['desc'] . '</h1>';
                    break;
                case 'title_h2':
                    echo '<h2>' . $field['desc'] . '</h2>';
                    break;
                case 'title_h3':
                    echo '<h3>' . $field['desc'] . '</h3>';
                    break;
                default:
                    echo '<p>' . $field['desc'] . '</p>';
                    break;
            }
        }

        echo '</div>';
    }
    //Before结构
    public function before_tags($field)
    {
        //CSS选择器
        $class = array();
        $class[] = 'form-field';
        $class[] = 'section-' . $field['type'];
        if (!empty($field['class'])) {
            $class[] = $field['class'];
        }
        $html = '<div class="' . implode(' ', $class) . '">';

        //选项名称
        if (!empty($field['title'])) {
            $html .= '<label class="field-label" for="' . $field['id'] . '">' . $field['title'] . '</label>';
        }
        $html .= '<div class="field-area">';

        return $html;
    }
    //After结构
    public function after_tags($field)
    {
        $html = '';
        //添加描述
        if (!empty($field['desc'])) {
            $html = '<p class="desc">' . $field['desc'] . '</p>';
        }

        $html .= '</div></div>';

        return $html;
    }
    //多重调用
    public function field_mult($used, $field)
    {
        $new_class = $this->class_name . $used;

        //检查默认值
        if (empty($field['default'])) {
            $field['default'] = '';
        }
        //直接提取方法
        if (!empty($field['id']) && class_exists($new_class)) {
            //New
            $class = new $new_class();

            return $class->$used($field);
        }
    }
    //循环 htmlspecialchars 方法处理多层数组
    function deep_htmlspecialchars($mixed, $quote_style = ENT_QUOTES, $charset = 'UTF-8')
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = self::deep_htmlspecialchars($value, $quote_style, $charset);
            }
        } elseif (is_string($mixed)) {
            $mixed = htmlspecialchars_decode($mixed, $quote_style);
        }
        return $mixed;
    }
    //检查选择器
    public function entry_select($field)
    {
        //内置查询
        if (!empty($field['sub_mode']) && $field['sub_mode'] != '') {
            $new_array = self::select_entries($field['sub_mode']);
        }
        //直接输出
        elseif (!empty($field['sub']) && is_array($field['sub']) && $field['sub'] != '') {
            $new_array = $field['sub'];
        } else {
            $new_array = array();
        }
        return $new_array;
    }
    //选择器的查询方法
    public function select_entries($value)
    {
        if ($value == '') return;

        //获取所有可显示的Taxonomy
        $taxonomies_names = get_taxonomies(array('show_ui' => true, '_builtin' => false), 'names');
        //将category、post_tag、nav_menu作为标记添加到数组，方便查询
        $taxonomies_names[] = 'category';
        $taxonomies_names[] = 'post_tag';
        $taxonomies_names[] = 'nav_menu';

        //获取所有可显示的Post
        $post_types = get_post_types(array('public' => true, '_builtin' => false), 'names');
        //将post、page作为标记添加到数组，方便查询
        $post_types[] = 'post';
        $post_types[] = 'page';

        //开始查询
        $entries = array();

        //如果是Post
        if (in_array($value, $post_types)) {
            //查询参数
            $t_args = array(
                'post_type' => $value,
                'post_parent' => 0
            );
            //返回
            $entries = self::get_posts_by_level($t_args);
        }
        //如果是Taxonomy
        elseif (in_array($value, $taxonomies_names)) {
            //查询参数
            $t_args = array(
                'taxonomy' => $value,
                'hide_empty' => false,
                'parent' => 0
            );
            //返回
            $entries = self::get_terms_by_level($t_args);
        }
        //如果是Sidebar
        elseif ($value == 'sidebar') {
            //获取wp_registered_sidebars
            global $wp_registered_sidebars;

            $sidebars = $wp_registered_sidebars;
            //遍历，返回id和name组成关联数组
            foreach ($sidebars as $sidebar) {
                $entries[$sidebar['id']] = $sidebar['name'];
            }
        }
        //如果是User
        elseif ($value == 'user') {
            //直接获取所有用户
            $all_users = get_users();
            //遍历，返回user_ID和user_login组成关联数组
            foreach ($all_users as $user) {
                $entries[$user->ID] = $user->user_login;
            }
        } else {
            $entries = $value;
        }

        return $entries;
    }
    //递归方法获取文章
    public function get_posts_by_level($args, $space = '')
    {
        $posts = array();
        //设置每页显示文章数999避免循环
        $args['posts_per_page'] = 999;
        //获取文章
        $top_posts = get_posts($args);

        if (!empty($top_posts)) {
            //遍历
            foreach ($top_posts as $post) {
                //将文章ID和文章标题存入$posts数组中
                $posts[$post->ID] = $post->post_title;

                //查询父级ID
                $args['post_parent'] = $post->ID;
                //递归此方法
                $child_posts = $this->get_posts_by_level($args);
                //遍历
                foreach ($child_posts as $key => $title) {
                    //存入$posts数组中
                    $posts[$key] = $space . $title;
                }
            }
        }
        //返回$posts
        return $posts;
    }
    //递归方法获取全部分类
    public function get_terms_by_level($args, $space = '')
    {
        $terms = array();
        //获取分类
        $top_terms = get_terms($args);

        if ($top_terms && !is_wp_error($top_terms)) {
            //遍历
            foreach ($top_terms as $term) {
                //将标签ID和标签名称存储在$terms数组中
                $terms[$term->term_id] = $term->name;
                //查询下一级联标签ID
                $args['parent'] = $term->term_id;
                //递归此方法
                $child_terms = $this->get_terms_by_level($args, $space);
                //遍历
                foreach ($child_terms as $key => $title) {
                    $terms[$key] = $space . $title;
                }
            }
        }
        //返回$terms
        return $terms;
    }
}
