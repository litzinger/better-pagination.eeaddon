# Better Pagination

This extension was created to replace the native pagination links e.g. /template-group/template/P1, with something... more usable. This will create sane, and normal pagination links such as /template-group/template?&page=1. The "page" parameter is still an offset, but it is easier to work with when writing PHP in your add-on, or if you use something like Super Globals or Switchee to reference GET variables. Best of all, it works with Strucure pages. No Freebie or other hacks necessary.

Note this add-on is free and technically unsupported. You can report issues at boldminded.com/support, but fixes will always be lower priority for me. Feel free to fork this or submit pull requests.

## Optional Configuration

If you want to change the GET parameter name used for pages change the page_name variable:

	$config['better_pagination']['page_name'] = 'page';

You can also rename the global variable used as the value of your offset parameter in your entries tag:

	$config['better_pagination']['offset_name'] = 'global:pagination_offset';

## Usage

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

## Result

	<li><a href="http://site.com/news" class="current">1</a></li>
	<li><a href="http://site.com/news?&page=16" >2</a></li>
	<li><a href="http://site.com/news?&page=32" >3</a></li>
	<li><a href="http://site.com/news?&page=48" >4</a></li>
	<li><a href="http://site.com/news?&page=16" title="Next" class="arrow-next pager-element">Next</a></li>

## 3rd Party Support

This also works with the REST module, and Solspace's Calendar module, but you must add the following hook to Calendar for it to work: https://gist.github.com/3219428

## Add-on Developers

You can use Better Pagination to add pagination to any custom module tag with very little effort. You will need to call the 
    
    public function my_module_tag()
    {
        // Tag data
        $tagdata = $this->EE->TMPL->tagdata;

        // Do all your fun stuff here

        // Parse Conditioanls
        $tagdata = $this->EE->functions->prep_conditionals($tagdata, $conds);

        // Assume no results
        $total_results = 0;

        // $vars will be your result array of data
        if (is_array($vars))
        {
            $total_results = count($vars);

            // Can use ee()->TMPL->fetch_param('offset') here instead
            $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;
            $limit  = isset($this->params['limit']) ? $this->params['limit'] : 10;

            // Slice the array to create the subset of data to show on the page
            // Alternatively you can do an additional query to fetch your subset
            // of data, but that requires an extra query to fetch the $total_results.
            // This is the simplest implementation.
            $vars = array_slice($vars, $offset, $limit);
        }

        // -------------------------------------------
        //  'better_pagination_abstract_result' hook
        // 
            if (ee()->extensions->active_hook('better_pagination_abstract_result'))
            {
                $vars = ee()->extensions->call('better_pagination_abstract_result', $vars, $total_results);
            }
        // 
        // -------------------------------------------
        
        $tagdata = $this->EE->TMPL->parse_variables($tagdata, $vars);
        
        // -------------------------------------------
        //  'better_pagination_abstract_tagdata' hook
        // 
            if (ee()->extensions->active_hook('better_pagination_abstract_tagdata'))
            {
                $tagdata = ee()->extensions->call('better_pagination_abstract_tagdata', $tagdata);
            }
        // 
        // -------------------------------------------

        return $tagdata;
    }

