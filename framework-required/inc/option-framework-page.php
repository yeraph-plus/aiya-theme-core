<?php
if (!defined('ABSPATH')) exit;

/**
 * AIYA-CMS Theme Options Framework 创建设置页面
 * 
 * @version 1.0
 **/

if (!class_exists('AYA_Framework_Options_Page')) exit;

class AYA_Framework_Options_Page extends AYA_Field_Action
{
    private $option_info;
    private $options;

    private $menu_page;
    private $menu_parent;
    private $page_title;
    private $menu_title;

    private $in_multisite;

    private $old_value;
    private $saved_value;

    private $enqueue_html = array();

    //监听JS加载
    public function enqueue_html()
    {
        $output = '';
        foreach ($this->enqueue_html as $id => $tem_html) {
            $output = '<script type="text/html" id="' . $id . '">' . $tem_html . '</script>';
        }

        echo $output;
    }

    public function __construct($option_conf, $option_info)
    {
        //验证用户是否为管理员
        if (!current_user_can('manage_options')) return;

        $this->option_info = $option_info;
        $this->options = $option_conf;

        $this->menu_page = $option_info['slug'];
        $this->page_title = (key_exists('page_title', $option_info) && !empty($option_info['page_title'])) ? $option_info['page_title'] : $option_info['title'];
        $this->menu_title = $option_info['title'];
        //判断子菜单
        $this->menu_parent = (key_exists('parent', $option_info) && !empty($option_info['parent'])) ? $option_info['parent'] : '';
        //定义保存点
        $this->saved_value = 'aya_opt_' . $option_info['slug'];
        //检查多站点
        $this->in_multisite = self::in_multisite($option_info);

        //多站点兼容
        add_action($this->in_multisite ? 'network_admin_menu' : 'admin_menu', array(&$this, 'add_admin_menu_page'));

        //定位页面
        if (isset($_GET['page']) && ($_GET['page'] == $this->menu_page)) {
            //加载
            add_action('admin_enqueue_scripts', array(&$this, 'enqueue_script'));
            add_action('admin_footer', array(&$this, 'enqueue_html'));
        }
    }
    //创建页面
    function add_admin_menu_page()
    {
        $menu_slug = $this->menu_page;
        $parent_slug = $this->menu_parent;
        $menu_title = $this->menu_title;
        $page_title = $this->page_title;

        if ($parent_slug == '') {
            add_menu_page($page_title, $menu_title,  'manage_options', $menu_slug, array(&$this, 'init_page'), '', 99);
        } else {
            add_submenu_page($parent_slug, $page_title,  $menu_title, 'manage_options', $menu_slug, array(&$this, 'init_page'), 0);
        }
    }
    //加载样式
    public function enqueue_script()
    {
        //加载JS文件
        wp_enqueue_style('aya-framework', AYA_CORE_URI . '/assects/css/framework-style.css');
        wp_enqueue_script('aya-framework', AYA_CORE_URI . '/assects/js/framework-main.js', '', '', true);
        wp_enqueue_style('codemirror', AYA_CORE_URI . '/assects/css/codemirror.css');
        wp_enqueue_script('codemirror', AYA_CORE_URI . '/assects/js/codemirror.js', '', '', true);

        //上传组件
        wp_enqueue_media();

        //颜色选择器
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_localize_script('aya-framework-ajax', 'aya_framework', array('ajax_url' => admin_url('admin-ajax.php')));
    }
    //检查多站点设置
    private function in_multisite($info)
    {
        if (is_multisite()) {
            //检查设置表单
            if (isset($info['in_multisite']) && $info['in_multisite'] == true) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    //定义执行顺序
    function init_page()
    {
        self::save_options_data();

        self::get_options_data();

        self::display_html();
    }
    //提取数据
    function get_options_data()
    {
        //多站点兼容
        if ($this->in_multisite) {
            $this->old_value[$this->option_info['slug']] = get_site_option($this->saved_value);
        } else {
            $this->old_value[$this->option_info['slug']] = get_option($this->saved_value);
        }
        //Fix
        $this->old_value = parent::deep_htmlspecialchars($this->old_value, ENT_QUOTES, 'UTF-8');

        foreach ($this->options as $key => $option) {
            //选项不存在ID则跳过
            if (isset($option['id']) && isset($this->old_value[$this->option_info['slug']][$option['id']])) {
                //格式化数据
                $this->options[$key]['default'] = $this->old_value[$this->option_info['slug']][$option['id']];
            }
        }
    }
    //保存数据
    function save_options_data()
    {
        //验证用户是否为管理员
        if (!current_user_can('manage_options')) return;

        //获取设置数据
        $new_value  = $this->old_value;

        $saved_message = '';

        //检查From表单
        if (isset($_REQUEST['aya_option_field']) && check_admin_referer('aya_option_action', 'aya_option_field')) {
            //清除旧数据
            if (!empty($_POST['aya_option_reset'])) {
                //多站点兼容
                if ($this->in_multisite) {
                    delete_site_option($this->saved_value);
                } else {
                    delete_option($this->saved_value);
                }
                //提示
                $saved_message = esc_html__('Options reseted.', 'AIYA');
            }
            //存入新数据
            if (!empty($_POST['aya_option_submit'])) {
                //array('option_name' => 'option_value');
                //处理数据
                foreach ($this->options as $option) {
                    //没有ID则跳过循环
                    if (!isset($option['id'])) {
                        continue;
                    }
                    $value = empty($_POST[$option['id']]) ? '' : $_POST[$option['id']];
                    //如果是输入框
                    if ($option['type'] == 'text' || $option['type'] == 'textarea') {
                        $value = wp_unslash($value);
                        $value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
                    }
                    //如果是复选框
                    elseif ($option['type'] == 'checkbox') {
                        $value = ($value == '') ? [] : $value;
                    }
                    //如果是数组
                    elseif ($option['type'] == 'array') {
                        $value = explode(',', $value);
                        $value = array_filter($value);
                    }
                    //如果是编辑器
                    elseif ($option['type'] == 'editor' || $option['type'] == 'code_editor') {
                        $value = wp_unslash($value);
                    }
                    //如果是设置组
                    elseif ($option['type'] == 'group') {
                        if ($value != '') {
                            $value = array_filter($value);
                        } else {
                            $value = '';
                        }
                    }
                    //其他
                    else {
                        $value = wp_unslash($value);
                    }

                    $new_value[$option['id']] = $value;
                }
                //防止重复提交
                if ($this->old_value != $new_value) {
                    //多站点兼容
                    if ($this->in_multisite) {
                        update_site_option($this->saved_value, $new_value);
                    } else {
                        update_option($this->saved_value, $new_value);
                    }
                    //提示
                    $saved_message = esc_html__('Options saved.', 'AIYA');
                } else {
                    //提示
                    //$saved_message = esc_html__('Options has already been saved.', 'AIYA');
                }
            }
        }
        //返回提示
        if ($saved_message != '') {
            return '<p class="success">' . $saved_message . '</p>';
        }
    }

    function display_html()
    {
        $before_html = '';
        $after_html = '';

        $before_html .= '<div class="wrap" id="framework-page">';

        $before_html .= '<div class="container framework-title">';
        $before_html .= '<h1>' . $this->option_info['title'] . '</h1>';
        if (!empty($this->option_info['desc'])) {
            $before_html .= '<p>' . $this->option_info['desc'] . '</p>';
        }
        $before_html .= '</div>';

        $before_html .= '<div class="container framework-content framework-wrap">';
        $before_html .= '<div class="framework-message" id="saved"></div>';

        //表单结构
        $before_html .= '<form method="post" id="from-wrap" action="#saved">';

        echo $before_html;

        $saved_button = false;
        //循环
        foreach ($this->options as $option) {
            //如果是文本框则转换一次数据
            if (in_array($option['type'], array('text', 'textarea',)) && (!empty($option['multiple_mdoe']) && $option['multiple_mdoe'] == false)) {
                $option['default'] = htmlspecialchars($option['default'], ENT_COMPAT, 'UTF-8');
            }
            //组件方法
            parent::field($option);
            //放置保存按钮
            if (!in_array($option['type'], array('callback', 'content', 'title_h1', 'title_h2', 'title_h3'))) {
                $saved_button = true;
            }
        }
        //保存按钮
        if ($saved_button) {
            $button_html = '<div class="framework-saved">';
            //Fix：检索表单的nonce隐藏字段
            wp_nonce_field('aya_option_action', 'aya_option_field');

            $button_html .= '<input type="submit" name="aya_option_submit" class="button-primary autowidth" value="Save Changes" />';
            $button_html .= '<input type="submit" name="aya_option_reset" class="button-secondary autowidth" value="Clear" />';

            $button_html .= '</div>';

            echo $button_html;
        }

        $after_html = '</form>';

        $after_html .= '</div>';
        $after_html .= '<div class="float-right d-md-block"></div>';
        $after_html .= '</div>';

        echo $after_html;
    }
}
