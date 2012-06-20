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
 * @link        http://boldminded.com & http://nerdery.com
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

        // Create cache
        if (! isset($this->EE->session->cache['better_pagination']))
        {
            $this->EE->session->cache['better_pagination'] = array();
        }
        $this->cache =& $this->EE->session->cache['better_pagination'];
    }
    

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
            'channel_module_create_pagination'  => 'channel_module_create_pagination',
            'channel_entries_query_result' => 'channel_entries_query_result'
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
        $this->EE->config->_global_vars[$this->offset_var] = $this->EE->input->get('page') ? $this->EE->input->get('page') : 0;

        return $session;
    }
    
    /**
     * channel_entries_query_result
     *
     * @param Instance of the current Channel->entries object
     * @param Current entry result array
     * @return null
     */
    public function channel_entries_query_result(&$channel, $query_result)
    {
        if (!isset($channel->pager_sql) OR $channel->pager_sql == '')
        {
            return $query_result;
        }
        
        $query = $channel->EE->db->query($channel->pager_sql);
        $count = ( !empty($query->num_rows) ) ? $query->num_rows : FALSE;

        // Only proceed if the option is set
        if ($this->cache['pagination']->paginate == TRUE AND $count > 0)
        {
            $this->_prep();
            
            // Initialize a new pagination object which does the hard work for us.
            $this->EE->pagination->initialize(array(
                'base_url'      => $this->base_url,
                'per_page'      => $this->per_page,
                'cur_page'      => $this->offset,
                'total_rows'    => $count,
                'prefix'        => '', // Remove that stupid P
                'num_links'     => 100,
                'uri_segment'   => 0,
                'query_string_segment' => $this->page_var,
                'page_query_string' => TRUE
            ));
            
            // $pagination->total_pages = $count;
            $this->cache['pagination']->current_page = $this->offset;

            $link_array = $this->EE->pagination->create_link_array();
            $link_array['total_pages'] = ceil($count / $this->per_page);
            $link_array['current_page'] = $this->offset;

            // Update the {paginate} tag pair and {pagination_links} variable with the new variables.
            $this->cache['pagination']->page_links = $this->EE->pagination->create_links();
            $this->cache['pagination']->template_data = $this->EE->TMPL->parse_variables($this->cache['pagination']->template_data, array($link_array));

            // Clean up empty page params from the URI - Thanks @adrienneleigh for the regex, again.
            $this->cache['pagination']->template_data = preg_replace("/((\?)?&amp;".$this->page_var ."=)(\D)/", "$3", $this->cache['pagination']->template_data);
        }

        return $query_result;
    }

    
    /**
     * calendar_events_create_pagination
     *
     * @param Instance of the current Calendar->events object
     * @param Current pagination_data array
     * @return null
     */
    public function calendar_events_create_pagination(&$events, $data)
    {
        // Only proceed if the option is set
        if ($data['paginate'] == TRUE AND $data['total_results'] > 0)
        {
            $this->_prep();
            
            // Initialize a new pagination object which does the hard work for us.
            $this->EE->pagination->initialize(array(
                'base_url'      => $this->base_url,
                'per_page'      => $this->per_page,
                'cur_page'      => $this->offset,
                'total_rows'    => $data['total_results'],
                'prefix'        => '', // Remove that stupid P
                'num_links'     => 100,
                'uri_segment'   => 0,
                'query_string_segment' => $this->page_var,
                'page_query_string' => TRUE
            ));
            
            $link_array = $this->EE->pagination->create_link_array();
            $link_array['total_pages'] = $data['total_pages'];
            $link_array['current_page'] = $this->offset;

            // Update the {paginate} tag pair and {pagination_links} variable with the new variables.
            $data['pagination_links'] = $this->EE->pagination->create_links();
            $data['pagination_array'] = $link_array;
            $data['paginate_tagpair_data'] = $this->EE->TMPL->parse_variables($data['paginate_tagpair_data'], array($link_array));

            // Clean up empty page params from the URI - Thanks @adrienneleigh for the regex, again.
            $data['paginate_tagpair_data'] = preg_replace("/((\?)?&amp;". $this->page_var ."=)(\D)/", "$3", $data['paginate_tagpair_data']);
        }

        return $data;
    }

    /**
     * channel_entries_tagdata
     *
     * @param Instance of the current Channel->entries object
     * @param Total results found to paginate over
     * @return null
     */
    public function channel_module_create_pagination(&$pagination, $count)
    {
        $this->EE->extensions->end_script = TRUE;
        $this->cache['pagination'] = $pagination;
    }

    /*
        Allow for variable override in your config file. 
        No need for a settings page for something this simple.
    */
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

    /*
        Common variables used in Entries or Calendar pagination
    */
    private function _prep()
    {
        $this->_set_variables();

        $params = $this->EE->TMPL->tagparams;

        // Current page is actually an offset value
        $this->offset = $this->EE->input->get($this->page_var) ? $this->EE->input->get($this->page_var) : 0;
        $this->per_page = isset($params['limit']) ? $params['limit'] : 100;
        // For Solspace Calendar support
        $this->per_page = isset($params['event_limit']) ? $params['event_limit'] : $this->per_page;
        
        // Grab any existing query string
        $query_string = (isset($_SERVER['QUERY_STRING']) AND $_SERVER['QUERY_STRING'] != '') ? '?'. $_SERVER['QUERY_STRING'] : '';
        
        // Set our base
        $this->base_url = isset($params['pagination_base']) ? $params['pagination_base'] : $this->EE->config->slash_item('site_url') . $this->EE->uri->uri_string . $query_string;

        // Make sure the base has a ? in it before CI->Pagination gets ahold of it or it will puke.
        $this->base_url = ! strstr($this->base_url, '?') ? $this->base_url .'?' : $this->base_url;
        
        // Clean up any page params in the query string so CI->Pagination doesnt add multiples (yeah, it's not very smart).
        $this->base_url = preg_replace("/&". $this->page_var ."=(\d+)|&". $this->page_var ."=/", "", $this->base_url);
        
        $this->EE->load->library('pagination');
    }

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
}

/* End of file ext.better_pagination.php */
/* Location: /system/expressionengine/third_party/better_pagination/ext.better_pagination.php */