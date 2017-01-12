<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Better Pagination Extension
 *
 * @package     ExpressionEngine
 * @subpackage  Addons
 * @category    Extension
 * @author      Brian Litzinger
 * @link        http://boldminded.com & http://nerdery.com
 */

class Better_pagination_ext {
    
    public $settings        = array();
    public $description     = 'Better pagination class, which uses query strings, and works with Structure. No hacks necessary.';
    public $docs_url        = '';
    public $name            = 'Better Pagination';
    public $settings_exist  = 'n';
    public $version         = '1.2';
    
    private $total_pages = 0;
    
    /**
     * @param   mixed   Settings array or empty string if none exist.
     */
    public function __construct($settings = '')
    {
        $this->settings = $settings;

        // Create cache
        if (! isset(ee()->session->cache['better_pagination'])) {
            ee()->session->cache['better_pagination'] = array();
        }
        $this->cache =& ee()->session->cache['better_pagination'];

        // Clean the page variable before the CI Pagination class uses it.
        $_GET['page'] = $this->clean($_GET['page']);
    }
    

    /**
     * @return void
     */
    public function activate_extension()
    {
        // Setup custom settings in this array.
        $this->settings = array();
        
        $hooks = array(
            'sessions_end'  => 'sessions_end',
            'channel_module_create_pagination'  => 'channel_module_create_pagination',
            'channel_entries_query_result' => 'channel_entries_query_result',
            'abstract_result' => 'better_pagination_abstract_result',
            'abstract_tagdata_end' => 'better_pagination_abstract_tagdata',

            // For Solspace Calendar support (if the hook is added)
            'calendar_events_create_pagination' => 'calendar_events_create_pagination',

            // REST module support
            'rest_result' => 'rest_result',
            'rest_tagdata_end' => 'rest_tagdata_end'
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

            ee()->db->insert('extensions', $data);         
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
        ee()->config->_global_vars[$this->offset_var] = ee()->input->get($this->page_var, true) ? ee()->input->get($this->page_var, true) : 0;

        return $session;
    }
    
    /**
     * @param Instance of the current Channel->entries object
     * @param Current entry result array
     * @return null
     */
    public function channel_entries_query_result(&$channel, $query_result)
    {
        // Make sure we're working with the correct result array 
        // if something else modified it.
        if (ee()->extensions->last_call)
        {
            $query_result = ee()->extensions->last_call;
        }

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

            // Re-build the pagination object otherwise the pagination
            // might not show up on the last page if there is 1 entry
            $channel->pagination->build($count, $this->per_page);
            
            $this->_initialize($count);

            $total_pages = ((int) $this->per_page == 1 AND $count > 1) ? $count : ceil($count / $this->per_page);
            
            $this->cache['pagination']->total_pages = $total_pages;
            $this->cache['pagination']->current_page = $this->offset;

            $link_array = ee()->pagination->create_link_array();
            $link_array['total_pages'] = $total_pages;
            $link_array['current_page'] = $this->offset;

            // Update the {paginate} tag pair and {pagination_links} variable with the new variables.
            $this->cache['pagination']->page_links = ee()->pagination->create_links();
            $this->cache['pagination']->template_data = ee()->TMPL->parse_variables($this->cache['pagination']->template_data, array($link_array));

            // Clean up empty page params from the URI - Thanks @adrienneleigh for the regex, again.
            $this->cache['pagination']->template_data = preg_replace("/((\?)?&amp;".$this->page_var ."=)(\D)/", "$3", $this->cache['pagination']->template_data);
        }

        return $query_result;
    }

    
    /**
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
            
            $this->_initialize($data['total_results']);            
            
            $link_array = ee()->pagination->create_link_array();
            $link_array['total_pages'] = $data['total_pages'];
            $link_array['current_page'] = $this->offset;

            // Update the {paginate} tag pair and {pagination_links} variable with the new variables.
            $data['pagination_links'] = ee()->pagination->create_links();
            $data['pagination_array'] = $link_array;
            $data['paginate_tagpair_data'] = ee()->TMPL->parse_variables($data['paginate_tagpair_data'], array($link_array));

            // Clean up empty page params from the URI - Thanks @adrienneleigh for the regex, again.
            $data['paginate_tagpair_data'] = preg_replace("/((\?)?&amp;". $this->page_var ."=)(\D)/", "$3", $data['paginate_tagpair_data']);
        }

        return $data;
    }

    public function rest_result($result, $total_results)
    {
        return $this->abstract_result($result, $total_results);
    }

    public function rest_tagdata_end($tagdata)
    {
        return $this->abstract_tagdata_end($tagdata);
    }

    /**
     * @param Result array of $vars from module tag
     * @param Total number of $vars in the array
     * @return modified result array
     */
    public function abstract_result($result, $total_results)
    {
        $this->_prep();

        $this->cache['total_rows'] = $total_results;
        $this->cache['total_pages'] = ceil($this->cache['total_rows'] / $this->params['limit']);

        // Set total_results on each row so the var parses
        foreach ($result as &$row)
        {
            $row['total_results'] = $total_results;
        }

        return $result;
    }

    /**
     * @param Template tag data within your module tag
     * @return modified $tagdata with pagination results
     */
    public function abstract_tagdata_end($tagdata)
    {
        $this->_prep();

        // Do we have a paginate parameter? If not, just stop.
        if ( ! isset($this->params['paginate']))
        {
            return $tagdata;
        }

        $paginate_tagdata = '';

        // Grab paginate tag if it exists
        preg_match_all("|".LD.'paginate.*?'.RD.'(.*?)'.LD.'/paginate'.RD."|s", $tagdata, $matches);

        if (isset($matches[1]) && isset($matches[1][0]))
        {
            $paginate_tagpair = trim($matches[0][0]);
            $paginate_tagdata = trim($matches[1][0]);

            // Remove the {paginate} tag pair from the $tagdata so it only occurs once.
            $tagdata = str_replace($paginate_tagpair, '', $tagdata);
        }

        $this->_initialize($this->cache['total_rows']);

        $link_array = ee()->pagination->create_link_array();
        $link_array['total_pages'] = $this->cache['total_pages'];
        $link_array['current_page'] = $this->offset;

        $data['pagination_links'] = ee()->pagination->create_links();
        $data['pagination_array'] = $link_array;
        $paginate_tagdata = ee()->TMPL->parse_variables($paginate_tagdata, array($link_array));

        // Determine if pagination needs to go at the top and/or bottom. 
        // Since we are not in the Channel parser we need to do this ourselves.
        if ($link_array['total_pages'] > 1)
        {
            switch ($this->params['paginate'])
            {
                case "top":
                    $tagdata = $paginate_tagdata.$tagdata;
                break;
                case "both":
                    $tagdata = $paginate_tagdata.$tagdata.$paginate_tagdata;
                break;
                case "bottom":
                default:
                    $tagdata = $tagdata.$paginate_tagdata;
            }
        }

        // Clean up empty page params from the URI - Thanks @adrienneleigh for the regex, again.
        $tagdata = preg_replace("/((\?)?&amp;". $this->page_var ."=)(\D)/", "$3", $tagdata);

        return $tagdata;
    }

    /**
     * @param Instance of the current Channel->entries object
     * @param Total results found to paginate over
     * @return null
     */
    public function channel_module_create_pagination(&$pagination, $count)
    {
        // ee()->extensions->end_script = TRUE; // Causes big issues
        $this->cache['pagination'] = $pagination;
    }


    private function _initialize($total_results)
    {
        // Initialize a new pagination object which does the hard work for us.
        ee()->pagination->initialize(array(
            'base_url'      => $this->base_url,
            'per_page'      => $this->per_page,
            'cur_page'      => $this->offset,
            'total_rows'    => $total_results,
            'prefix'        => '', // Remove that stupid P
            'num_links'     => 100,
            'uri_segment'   => 0,
            'query_string_segment' => $this->page_var,
            'page_query_string' => TRUE
        ));
    }

    /**
     * Allow for variable override in your config file.
     * No need for a settings page for something this simple.
     *
     * @return void
     */
    private function _set_variables()
    {
        // Our default
        $this->offset_var = 'global:pagination_offset';
        $this->page_var = 'page';
        
        $config = ee()->config->item('better_pagination');
        
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

    /**
     * Common variables used in Entries or Calendar pagination
     */
    private function _prep()
    {
        $this->_set_variables();

        $this->params = ee()->TMPL->tagparams;

        $this->params['limit'] = isset($this->params['limit']) ? $this->params['limit'] : 100;

        // Current page is actually an offset value
        $this->offset = ee()->input->get($this->page_var, true) ? ee()->input->get($this->page_var, true) : 0;
        $this->per_page = $this->params['limit'];

        // For Solspace Calendar support
        $this->per_page = isset($this->params['event_limit']) ? $this->params['event_limit'] : $this->per_page;
        
        // Grab any existing query string
        $query_string = (isset($_SERVER['QUERY_STRING']) AND $_SERVER['QUERY_STRING'] != '') ? '?'. $_SERVER['QUERY_STRING'] : '';
        $default_url = ee()->config->slash_item('site_url') . ee()->uri->uri_string . $query_string;

        // Set our base
        $this->base_url = isset($this->params['pagination_base']) ? $this->params['pagination_base'] : $default_url;

        // Make sure the base has a ? in it before CI->Pagination gets ahold of it or it will puke.
        $this->base_url = ! strstr($this->base_url, '?') ? $this->base_url .'?' : $this->base_url;
        
        // Clean up any page params in the query string so CI->Pagination doesnt add multiples (yeah, it's not very smart).
        $this->base_url = preg_replace("/&". $this->page_var ."=(\d+)|&". $this->page_var ."=/", "", $this->base_url);

        // Prevent query string script executions
        $this->base_url = $this->clean($this->base_url);
        
        ee()->load->library('pagination');
    }

    /**
     * @param $string
     * @return string
     */
    private function clean($string)
    {
        $string = str_replace(array('{', '}'), '', $string);
        return ee()->security->xss_clean($string);
    }

    /**
     * @return void
     */
    function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    /**
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
