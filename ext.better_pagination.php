<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package     ExpressionEngine
 * @author      ExpressionEngine Dev Team
 * @copyright   Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license     http://expressionengine.com/user_guide/license.html
 * @link        http://expressionengine.com
 * @since       Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * Better Pagination Extension
 *
 * @package     ExpressionEngine
 * @subpackage  Addons
 * @category    Extension
 * @author      Brian Litzinger
 * @link        http://boldminded.com / http://nerdery.com
 */


/*
    Usage

    {exp:channel:entries
        channel="blog" 
        dynamic="no"
        limit="6"
        offset="{global:pagination_offset}"
        paginate="bottom"}
    
        ...

        {paginate}
            {previous_page}
                <a href="{pagination_url}" title="Previous" class="arrow-prev pager-element">Previous {!-- Or {text} --}</a>
            {/previous_page}
            
            {page}
                <a href="{pagination_url}" {if current_page}class="current"{/if}>{pagination_page_number}</a>
            {/page}
        
            {next_page}
                <a href="{pagination_url}" title="Next" class="arrow-next pager-element">Next  {!-- Or {text} --}</a>
            {/next_page}
        {/paginate}

    {/exp:channel:entries}
*/

class Better_pagination_ext {
    
    public $settings        = array();
    public $description     = 'Better pagination class, which uses query strings, and works with Structure. No hacks necessary.';
    public $docs_url        = '';
    public $name            = 'Better Pagination';
    public $settings_exist  = 'n';
    public $version         = '1.0';
    
    private $EE;
    
    /**
     * Constructor
     *
     * @param   mixed   Settings array or empty string if none exist.
     */
    public function __construct($settings = '')
    {
        $this->EE =& get_instance();
        $this->settings = $settings;
    }// ----------------------------------------------------------------------
    
    /**
     * Activate Extension
     *
     * This function enters the extension into the exp_extensions table
     *
     * @see http://codeigniter.com/user_guide/database/index.html for
     * more information on the db class.
     *
     * @return void
     */
    public function activate_extension()
    {
        // Setup custom settings in this array.
        $this->settings = array();
        
        $hooks = array(
            'sessions_end'  => 'sessions_end',
            'channel_module_create_pagination'  => 'channel_module_create_pagination'
        );

        foreach ($hooks as $hook => $method)
        {
            $data = array(
                'class'     => __CLASS__,
                'method'    => $method,
                'hook'      => $hook,
                'settings'  => serialize($this->settings),
                'version'   => $this->version,
                'enabled'   => 'y'
            );

            $this->EE->db->insert('extensions', $data);         
        }
    }   

    // ----------------------------------------------------------------------
    
    /**
     * channel_entries_tagdata
     *
     * @param Instance of the session object
     * @return Unmodified session object
     */
    public function sessions_end($session)
    {
        $this->_set_variables();
        
        // Set this so you can use it in {exp:channel:entries offset="{global:pagination_offset}"}
        $this->EE->config->_global_vars[$this->offset_var] = $this->EE->input->get('page', 0);
        
        return $session;
    }
    
    // Allow for variable override in your config file. 
    // No need for a settings page for something this simple.
    private function _set_variables()
    {
        // Our default
        $this->offset_var = 'global:pagination_offset';
        $this->page_var = 'page';
        
        $config = $this->EE->config->item('better_pagination');
        
        // User defined vars.
        if (isset($config['offset_name']))
        {
            $this->offset_var = $config['offset_name'];
        }
        
        if (isset($config['page_name']))
        {
            $this->page_var = $config['page_name'];
        }
    }
    
    // ----------------------------------------------------------------------
    
    /**
     * channel_module_create_pagination
     *
     * @param Instance of the current Channel->entries object
     * @return null
     */
    public function channel_module_create_pagination(&$channel)
    {
        $channel->EE->extensions->end_script = TRUE;

        if (!isset($channel->pager_sql) OR $channel->pager_sql == '')
        {
            return;
        }
        
        $query = $channel->EE->db->query($channel->pager_sql);
        $count = ( !empty($query->num_rows) ) ? $query->num_rows : FALSE;

        // Only proceed if the option is set
        if ($channel->paginate == TRUE)
        {
            $this->_set_variables();
            
            $params = $this->EE->TMPL->tagparams;
            
            $page_param = $this->page_var;
            
            // Current page is actually an offset value
            $offset = $this->EE->input->get($page_param, 0);
            $per_page = isset($params['limit']) ? $params['limit'] : 100;
            
            // Grab any existing query string
            $query_string = (isset($_SERVER['QUERY_STRING']) AND $_SERVER['QUERY_STRING'] != '') ? '?'. $_SERVER['QUERY_STRING'] : '';
            
            // Set our base
            $base_url = isset($params['pagination_base']) ? $params['pagination_base'] : $this->EE->config->slash_item('site_url') . $this->EE->uri->uri_string . $query_string;

            // Make sure the base has a ? in it before CI->Pagination gets ahold of it or it will puke.
            $base_url = ! strstr($base_url, '?') ? $base_url .'?' : $base_url;
            
            //Clean up any page params in the query string so CI->Pagination doesnt add multiples (yeah, it's not very smart).
            $base_url = preg_replace("/&". $page_param ."=(\d+)|&". $page_param ."=/", "", $base_url);
            
            $this->EE->load->library('pagination');
            $this->pagination = $this->EE->pagination;
            
            $total_pages = ceil($count / $per_page);
            
            // Initialize a new pagination object which does the hard work for us.
            $this->pagination->initialize(array(
                'base_url'      => $base_url,
                'per_page'      => $per_page,
                'total_rows'    => $count,
                'num_links'     => 100,
                'uri_segment'   => 0,
                'query_string_segment' => $page_param,
                'page_query_string' => TRUE
            ));
            
            $channel->total_pages = $total_pages;
            $channel->current_page = $offset;
            
            $link_array = $this->pagination->create_link_array();
            $link_array['total_pages'] = $total_pages;
            $link_array['current_page'] = $offset; 
            
            // Update the {paginate} tag pair and {pagination_links} variable with the new variables.
            $channel->pagination_links = $this->pagination->create_links();
            $channel->paginate_data = $this->EE->TMPL->parse_variables($channel->paginate_data, array($link_array));
        }
    }

    // ----------------------------------------------------------------------

    /**
     * Disable Extension
     *
     * This method removes information from the exp_extensions table
     *
     * @return void
     */
    function disable_extension()
    {
        $this->EE->db->where('class', __CLASS__);
        $this->EE->db->delete('extensions');
    }

    // ----------------------------------------------------------------------

    /**
     * Update Extension
     *
     * This function performs any necessary db updates when the extension
     * page is visited
     *
     * @return  mixed   void on update / false if none
     */
    function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }
    }   
    
    // ----------------------------------------------------------------------
}

/* End of file ext.better_pagination.php */
/* Location: /system/expressionengine/third_party/better_pagination/ext.better_pagination.php */