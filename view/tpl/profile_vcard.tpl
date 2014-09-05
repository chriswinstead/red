<div class="vcard">

	{{if $profile.edit}}
	<div class="dropdown">
	<a class="profile-edit-side-link dropdown-toggle" data-toggle="dropdown" title="{{$profile.edit.3}}" href="#" ><i class="icon-pencil btn btn-default btn-xs" title="{{$profile.edit.1}}" ></i></a>
	<ul class="dropdown-menu" role="menu">
		{{foreach $profile.menu.entries as $e}}
		<li>
			<a href="profiles/{{$e.id}}"><img src='{{$e.photo}}'>{{$e.profile_name}}</a>
		</li>
		{{/foreach}}
		<li><a href="profile_photo" >{{$profile.menu.chg_photo}}</a></li>
		{{if $profile.menu.cr_new}}<li><a href="profiles/new" id="profile-listing-new-link">{{$profile.menu.cr_new}}</a></li>{{/if}}
	</ul>
	</div>
	{{/if}}

	<div class="fn label">{{$profile.name}}{{if $profile.online}} <i class="icon-asterisk online-now" title="{{$profile.online}}"></i>{{/if}}</div>
		
	{{if $pdesc}}<div class="title">{{$profile.pdesc}}</div>{{/if}}
	<div id="profile-photo-wrapper"><img class="photo" width="175" height="175" src="{{$profile.photo}}?rev={{$profile.picdate}}" alt="{{$profile.name}}"></div>



	{{if $location}}
		<dl class="location"><dt class="location-label">{{$location}}</dt> 
		<dd class="adr">
			{{if $profile.address}}<div class="street-address">{{$profile.address}}</div>{{/if}}
			<span class="city-state-zip">
				<span class="locality">{{$profile.locality}}</span>{{if $profile.locality}}, {{/if}}
				<span class="region">{{$profile.region}}</span>
				<span class="postal-code">{{$profile.postal_code}}</span>
			</span>
			{{if $profile.country_name}}<span class="country-name">{{$profile.country_name}}</span>{{/if}}
		</dd>
		</dl>
	{{/if}}

	{{if $gender}}<dl class="mf"><dt class="gender-label">{{$gender}}</dt> <dd class="x-gender">{{$profile.gender}}</dd></dl>{{/if}}
	

	{{if $marital}}<dl class="marital"><dt class="marital-label"><span class="heart">&hearts;</span>{{$marital}}</dt><dd class="marital-text">{{$profile.marital}}</dd></dl>{{/if}}

	{{if $homepage}}<dl class="homepage"><dt class="homepage-label">{{$homepage}}</dt><dd class="homepage-url"><a href="{{$profile.homepage}}" >{{$profile.homepage}}</a></dd></dl>{{/if}}


{{if $connect}}
<a href="{{$connect_url}}" class="rconnect"><i class="icon-plus connect-icon"></i> {{$connect}}</a>
{{/if}}
	
</div>
<div id="vcard-end"></div>

{{$chanmenu}}

{{$contact_block}}


