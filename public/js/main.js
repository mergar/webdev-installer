main={
	working:false,

	init:function()
	{
		wizard.init();
		timezone.init();
		network.init();
		$('.genpass').bind('click',this.pwgen);
		$('.yelbut.install').bind('click',this.install);
		$('.yelbut.reboot').bind('click',this.reboot);
	},

	pwgen:function()
	{
		$.post('/',{'iface':'','mode':'PWGen'}).done(function(data){
			var data=$.parseJSON(data);
			$('#pass').val(data.password);
			$('#pass_rep').val('');
		});
	},

	install:function(event)
	{
		if(main.working) return;
		main.working=true;

		$(this).addClass('pressed');

		main.iconsInit();

		var lds=$('.inf-area.hdd .i-list input:checked');
		if(lds.length<1)
		{
			main.scrollTo('.inf-area.hdd');
			alert('You\'re not select HDD for install!');
			return;
		}
/*
		var tz=$('#tzsel').val();
		if(tz=='')
		{
			main.scrollTo('.inf-area.locale');
			alert('You\'re not select your Timezone!');
			return;
		}
*/
		var pass=$('#pass').val();
		var pass_rep=$('#pass_rep').val();
		if(pass=='')
		{
			alert('You\'re not type password for root!');
			return;
		}
		if(pass!=pass_rep)
		{
			alert('Passwords are different! Type the passwords again!');
			return;
		}

		//alert('it\'s ok!');
		var cl=$('#net-form input.netcard:checked').parent().attr('class');
		var iface=cl.replace(/card-/,'');

		var inf=$('#inf-'+iface);
		var dhcp=('.dhcpd',inf);
		if(!dhcp.is(':checked'))
		{
			var ip4=$('.ip4',inf).val();
			var ip6=$('.ip6',inf).val();
			if(ip4=='' && ip6=='')
			{
				alert('IPv4 or IPv6 settings is mandatory!');
				return;
			}
		}

		var posts=$('#net-form #inf-'+iface).serializeArray();
		posts=posts.concat($('.inf-area.hdd input:checked').serializeArray());
		posts.push({'name':'timezone','value':$('#tzsel').val()});
		posts.push({'name':'password','value':$('#pass').val()});
		posts.push({'name':'iface','value':iface});
		posts.push({'name':'mode','value':'startInstall'});

		$.post('/',posts).done(function(data){
			var data=$.parseJSON(data);
			if(typeof data.install_starting !='undefined')
			{
				if(data.install_starting)
				{
					wizard.nextPage();
					var d=new Date();
					main.startTime=d.getTime();
					main.progress();
				}
			}
			if(typeof data.error != 'undefined')
			{
				if(data.error)
					alert(data.error_messages);
				else
					alert('Installation is running!');
			}
			$('.yelbut.install').removeClass('pressed');
			main.working=false;
		});
	},

	reboot:function(event)
	{
		if(main.working) return;
		main.working=true;

		$(this).addClass('pressed');

		var posts={'mode':'reboot','iface':''};
		$.post('/',posts).done(function(data){
			var data=$.parseJSON(data);
			if(typeof data.error != 'undefined')
			{
				if(data.error)
					alert(data.error_messages);
				else
					alert('OS is rebooting now! Wait a few minutes.');
				$('.yelbut.reboot').removeClass('pressed');
			}
			main.working=false;
		});
	},

		scrollTo:function(id)
	{
		var obj=$(id);
		if(!obj)return;
		var pos=$(obj).position();
		var st=$('.wiz-page.p1').scrollTop();
		$('.wiz-page.p1').scrollTop(pos.top+st);
	},

	startTime:0,
	elapsedTime:function()
	{
		var d=new Date();
		var t=d.getTime();
		var ms=t-main.startTime;
		return main.ms2time(ms);
	},
	ms2time:function(ms)
	{
		var secs=ms/1000;
		ms=Math.floor(ms%1000);
		ms=(ms+'').substring(0,2);
		var minutes=secs/60;
		secs=Math.floor(secs%60);
		var hours=minutes/60;
		minutes=Math.floor(minutes%60);
		hours=Math.floor(hours%24);
		return hours + ":" + minutes + ":" + secs + "." + ms;
	},
	progress:function()
	{
		$.get('/installprogress',main.progressOk);
	},
	progressOk:function(data)
	{
		$('#time-txt').html(main.elapsedTime());
		var inf=data.split(':',3);
		var component=inf[0];
		var percent=parseInt(inf[1]);
		var message= $.trim(inf[2]);
		if(percent==-1)
		{
			alert('During installation, an error occurred!');
			return;
		}

		if(percent>=100)
		{
			$('#progress-bar .expand').animate({'width':percent+'%'},1000,function(){
				$('#percent-txt').html('100%');
				main.iconUpdate(component);
				wizard.nextPage();
			});
			return;
		}

		$('#percent-txt').html(percent+'%');

		var st=$('#progress-bar .expand')[0].style.width;
		if($.trim(st)=='') st=0;
		var val=parseInt(st);
		if(percent>val)
		{
			$('#progress-bar .expand').stop().animate({'width':percent+'%'},1000);
			main.iconUpdate(component);
		}
		window.setTimeout(main.progress,500);
	},

	lastPic:'',
	iconsInit:function()
	{
		main.pics={
			'fs':{name:'filesystem',id:'.ico.fs',completed:false},
			'os':{name:'operation system',id:'.ico.os',completed:false},
			'pkg':{name:'packages',id:'.ico.pkg',completed:false},
			'cfg':{name:'configuration',id:'.ico.cfg',completed:false}
		};
		for(var key in main.pics)
		{
			var obj=$(main.pics[key]['id']);
			if(obj) main.pics[key]['obj']=obj;
		}

	},
	iconUpdate:function(icon)
	{
		if(icon==main.lastPic) return;
		main.lastPic=icon;
		for(var key in main.pics)
		{
			console.log(icon + ' == ' + key + "\n");
			if(icon==key)
			{
				for(var key1 in main.pics)
				{
					if(!main.pics[key1]['completed'])
					{
						$(main.pics[key1]['obj']).addClass('completed');
						main.pics[key1]['completed']=true;
					}
					if(icon==key1) return;
				}
			}
		}
	},

	getKey:function(value)
	{
		for(var key in this)
		{
			if(this[key] == value) { return key; }
		}
		return null;
	}
}

wizard={

	mainContent:null,
	content:null,
	pages:[],
	pagesCount:0,
	pageCurrent:0,
    init:function()
    {
		this.content=$('#content');
		this.pages=$('#content .wiz-page');
		this.pagesCount=this.pages.length;
		this.mainContent=$(this.content).parent();
		this.resize();
    },

	oldHeight:0,
	oldWidth:0,
    resize:function()
    {
		var wdt=$(window).width();
		var hgt=$(window).height();
		var h_hgt=$('header.header').outerHeight();
		var f_hgt=$('footer.footer').outerHeight();
		if(this.oldHeight != hgt || this.oldWidth != wdt)
		{
			var newHeightCnt=hgt-h_hgt-f_hgt-
				parseInt($(this.content).css('padding-bottom'));
			var newHeight=newHeightCnt-
				parseInt($(this.mainContent).css('padding-bottom'));
			var newHeightCnt=newHeightCnt-
				parseInt($(this.content).css('padding-top'));

			$(this.mainContent).height(newHeight);

			$(this.content).height(newHeightCnt);
			$(this.content).width(wdt*this.pagesCount);

			$(this.pages).each(function(){$(this).width(wdt)});

			var left=wdt*this.pageCurrent*-1;
			$('#content').css({'left':left});

			this.oldHeight=hgt;
			this.oldWidth=wdt;
		}
    },

	nextPage:function()
	{
		if(this.pageCurrent>=(this.pagesCount-1)) return;
		this.pageCurrent++;
		var left=this.oldWidth*this.pageCurrent*-1;
		$('#content').animate({'left':left});

		/*
		if(this.pageCurrent==1)
		{
			$('#progress-bar .expand').animate({'width':'100%'},4000);
		}else{
			$('#progress-bar .expand').css({'width':0});
		}
		*/
	},

	prevPage:function()
	{
		if(this.pageCurrent<1) return;
		this.pageCurrent--;
		var left=this.oldWidth*this.pageCurrent*-1;
		$('#content').animate({'left':left});

		if(this.pageCurrent!=1)
		{
			$('#progress-bar .expand').css({'width':0});
			$('#progress-area .icons span').each(function(){$(this).removeClass('completed');});
		}
	}

};

timezone={
	pagesCount:0,
	pageCurrent:0,
	init:function()
	{
		$('div.timezones').bind('click',{obj:this},function(event){
			var obj=event.data.obj;
			obj.onclick(event.target);
		});
		$('div.timezones div.right').bind('mousewheel DOMMouseScroll',{obj:this},function(event,delta){
			var obj=event.data.obj;
			var e=event.originalEvent;
			var delta=e.wheelDelta>0||e.detail<0?'up':'dn';
			if(delta=='up') obj.goPrev(); else obj.goNext();
			event.preventDefault();
		});
		$.getJSON('tmz.json',function(data){
			$('#tzsel').autocomplete({source:data});
		});
		this.setPagesCount();
	},

	onclick:function(tag)
	{
		if($(tag).hasClass('town'))
		{
			var cName=$(tag).attr('class');
			cName=cName.replace(/([\s]*town[\s]*|[\s]*n[0-9]+[\s]*)/g,'');
			var timezone=this.ucfirst(cName)+'/'+$(tag).html();
			$('#tzsel').val(timezone);
		}
		if($(tag).parent().hasClass('regions') && !$(tag).hasClass('sel'))
		{
			if(tag.className!='clean')
			{
				var cName=$(tag).attr('class');
				var parent=$(tag).parent();
				$('li',parent).each(function(){$(this).removeClass('sel')});
				$(tag).addClass('sel');
				$('div.timezones div.letters span.sel').removeClass('sel');
				$('#ul-timezone').attr('class','tmz '+cName);
				this.sort('');
				this.goPage(0);
				this.pageCurrent=0;
			}
		}
		if($(tag).hasClass('lt') && !$(tag).hasClass('sel'))
		{
			var parent=$(tag).parent();
			$('span',parent).each(function(){$(this).removeClass('sel')});
			$(tag).addClass('sel');
			$('div.timezones ul.regions li.sel').removeClass('sel');
			var letter=$(tag).attr('class');
			var res=letter.match(/n[0-9]+/);
			$('#ul-timezone').attr('class','tmz '+res[0]);
			this.sort(res[0]);
			this.goPage(0);
			this.pageCurrent=0;
		}
		if($(tag).hasClass('page-pill'))
		{
			this.pageCurrent=$(tag).index();
			this.goPage(this.pageCurrent);
		}
		this.setPagesCount();
	},

	goPage:function(index)
	{
		var pills=$('div.timezones div.pages-pill span');
		$(pills).each(function(){$(this).removeClass('sel')});
		$(pills[index]).addClass('sel');
		var tz=$('#ul-timezone').parent();
		var top=$(tz).height()*index*-1;
		$('#ul-timezone').stop().animate({'top':top});
	},
	goNext:function()
	{
		if(this.pageCurrent>=(this.pagesCount-1)) return;
		this.pageCurrent=this.pageCurrent+1;
		this.goPage(this.pageCurrent);
	},
	goPrev:function()
	{
		if(this.pageCurrent<=0) return;
		this.pageCurrent=this.pageCurrent-1;
		this.goPage(this.pageCurrent);
	},

	setPagesCount:function()
	{
		var height=$('div.timezones div.tmz.cnt').height();
		var cl=$('#ul-timezone').attr('class');
		cl=cl.replace(/[\s]*tmz[\s*]/g,'');
		var ln=$('#ul-timezone.'+cl+' li.'+cl).length;
		this.pagesCount=Math.ceil(ln/30);

		var pills=$('div.pages-pill span');
		for(n=0,nl=pills.length;n<nl;n++)
		{
			if(n>=this.pagesCount)
			{
				$(pills[n]).css({'display':'none'});
			}else{
				$(pills[n]).css({'display':'inline-block'});
			}
		}
	},

	sort:function(letter)
	{
		if(letter!='') letter='.'+letter;
		$('#ul-timezone'+letter+' li'+letter).sort(this.sortAlpha).appendTo($('#ul-timezone'));
	},
	sortAlpha:function(a,b)
	{
		return a.innerHTML.toLowerCase() > b.innerHTML.toLowerCase() ? 1 : -1;
	},

	ucfirst:function(str)
	{
		var f = str.charAt(0).toUpperCase();
		return f + str.substr(1, str.length-1);
	}
};

network={
	init:function()
	{
		var cards=$('.netcard');
		for(n=0,nl=cards.length;n<nl;n++)
		{
			$(cards[n]).bind('click',function(){
				$('#net-form fieldset').each(function(){$(this).removeClass('checked');});
				var par=$(this).parent();
				$('fieldset',par).addClass('checked');
			});
		}
		var fs=$('#net-form fieldset');
		for(n=0,nl=fs.length;n<nl;n++)
		{
			$(fs[n]).bind('click',{obj:this},function(event){
				var obj=event.data.obj;
				obj.onclick(event.currentTarget,event.target);
			});
		}
		this.updateForm();
	},

	onclick:function(obj,target)
	{
		$('span.dhcp-errmsg',obj).html('');
		if(target.type!='checkbox') return;
		var id=obj.id;
		id=id.replace('inf-','');
		this.checkInputs(obj);
	},

	checkInputs:function(obj)
	{
		var cbx=$('input[type="checkbox"].dhcpd',obj);
		var els=$('input[type="text"]',obj);
		if(cbx.length==1 && $(cbx).is(':checked'))
		{
			$(els).each(function(){$(this).prop('disabled', true);});
			$('input[type="button"]',obj).prop('disabled',false);
		}else{
			$(els).each(function(){$(this).prop('disabled', false);});
			$('input[type="button"]',obj).prop('disabled',true);
		}
	},

	updateForm:function()
	{
		var flds=$('#net-form fieldset');
		for(n=0,nl=flds.length;n<nl;n++)
		{
			this.checkInputs(flds[n]);
		}
	},

	check:function(iface)
	{
		$('.wait-over').css({'display':'block'});
		$.post('/',{'iface':iface,'mode':'checkDHCP'}).done(function(data){
			network.fillData($.parseJSON(data),iface);
			$('.wait-over').css({'display':'none'});
		});
	},

	fillData:function(data,iface)
	{
		//{"nic":"re0","defnic4":"","defnic6":"","ip4":"10.0.0.2","gw4":"","ip6":"","gw6":"","mask4":"255.255.255.0","mask6":"","dhcpd":"YES"}
		var obj=$('#net-form #inf-'+iface);
		if(!obj) return;
		if(data.dhcpd.toLowerCase() == 'no')
		{
			$('span.dhcp-errmsg',obj).html(data.errmsg);
			$('input[type="checkbox"]',obj).prop('checked',false);
			this.checkInputs(obj);
		}else{
			$('span.dhcp-errmsg',obj).html(data.errmsg);
		}
		var els=$('input[type="text"]',obj);
		for(n=0,nl=els.length;n<nl;n++)
		{
			var name=$(els[n]).attr('name');
			var r=name.match(/network\[[^\]]*\]\[([^\]]*)\]/);
			var name=r[1];
			if(data[name] != 'undefined') $(els[n]).val(data[name]);
		}
	}
};

$(document).ready(function(){main.init();});
$(window).resize(function(){wizard.resize();});
