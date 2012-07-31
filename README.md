# Better Pagination

This extension was created to replace the native pagination links e.g. /template-group/template/P1, with something... more usable. This will create sane, and normal pagination links such as /template-group/template?&page=1. The "page" parameter is still an offset, but it is easier to work with when writing PHP in your add-on, or if y ou use something like Super Globals or Switchee to reference GET variables. Best of all, it works with Strucure pages. No Freebie or ohter hacks necessary.

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

