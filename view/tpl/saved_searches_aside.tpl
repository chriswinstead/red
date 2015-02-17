<div class="widget" id="saved-search-list">
	<h3 id="search">{{$title}}</h3>
	{{$searchbox}}
	
	<ul id="saved-search-ul" class="nav nav-pills nav-stacked" >
		{{foreach $saved as $search}}
		<li id="search-term-{{$search.id}}" class="saved-search-li clear">
			<a title="{{$search.delete}}" class="pull-right" onclick="return confirmDelete();" id="drop-saved-search-term-{{$search.id}}" href="network/?f=&amp;remove=1&amp;search={{$search.encodedterm}}"><i id="dropicon-saved-search-term-{{$search.id}}" class="icon-remove drop-icons iconspacer savedsearchdrop saved-search-icon" ></i></a>
			<a id="saved-search-term-{{$search.id}}" class="savedsearchterm{{if $search.selected}} search-selected{{/if}}" href="network/?f=&amp;search={{$search.encodedterm}}">{{$search.displayterm}}</a>
		</li>
		{{/foreach}}
	</ul>
	<div class="clear"></div>
</div>
