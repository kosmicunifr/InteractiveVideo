il.InteractiveVideoOverlayMarker = (function (scope) {
	'use strict';

	var pub = {
		actual_id : null
	}, pro = {
		default_color : '#FF0000',
		stroke_width : 4,
		marker_class  : 'magic_marker iv_svg_marker'
	}, pri = {
		'rect_prototype' : [
			'iv_mk_scale',
			'iv_mk_color_fill'
		],
		'circle_prototype' : [
			'iv_mk_width',
			'iv_mk_height',
			'iv_mk_rotate',
			'iv_mk_color_fill'
		],
		'arrow_prototype' : [
			'iv_mk_width',
			'iv_mk_height',
			'iv_mk_stroke',
			'iv_mk_color'
		],
		'line_prototype' : [
			'iv_mk_width',
			'iv_mk_height',
			'iv_mk_scale',
			'iv_mk_color_fill'
		],
		actual_marker : null,
		editScreen : false
	};

	pub.attachListener = function()
	{
		pro.attachSingleObjectListener('btn_rect', 'rect_prototype');
		pro.attachSingleObjectListener('btn_circle', 'circle_prototype');
		pro.attachSingleObjectListener('btn_arrow', 'arrow_prototype');
		pro.attachSingleObjectListener('btn_line', 'line_prototype');
		pro.attachStyleEvents();
		pro.attachSubmitCancelListener();
	};

	pub.checkForEditScreen = function()
	{
		var obj = $('#fake_marker');
		if(obj.length >= 1)
		{
			if(obj.val().length > 0)
			{

				var element = obj.val();
				pro.removeButtons();

				il.InteractiveVideoPlayerAbstract.addOnReadyFunction(
					(function ()
						{
							if(il.InteractiveVideoPlayerAbstract.config.external === false)
							{
								var sec = il.InteractiveVideoPlayerFunction.getSecondsFromTime($('#comment_time').val());
								il.InteractiveVideoPlayerAbstract.jumpToTimeInVideo(sec);
							}
							$('#ilInteractiveVideo').parent().attr('class', 'col-sm-6');
							pro.initialiseExistingMarker();
						}
					)
				);

				$('#ilInteractiveVideoOverlay').html(element);
				$('#add_marker_chk').click();
				$('.add_marker_selector').show( 'fast' );
				pri.editScreen = true;
			}
		}
		else
		{
			pub.attachListener();
		}
	};

	pro.initialiseExistingMarker = function()
	{
		var obj = $('.magic_marker');
		var type, proto;
		if($(obj).is('rect'))
		{
			type = 'rect';
			proto = 'rect_prototype';
		}
		else if($(obj).is('circle'))
		{
			type = 'circle';
			proto = 'circle_prototype';
		}
		else if($(obj).is('path'))
		{
			type = 'path';
			proto = 'arrow_prototype';
		}
		else if($(obj).is('line'))
		{
			type = 'line';
			proto = 'line_prototype';
		}
		var svg = SVG('ilInteractiveVideoOverlay');
		var marker = svg.select(type + '.magic_marker');
		var id = pro.getUniqueId();
		marker.id(id);
		marker.draggable();
		
		pri.actual_marker = marker;
		pro.hideMakerToolBarObjectsForForm(proto);
		pro.attachStyleEvents();

		if(pri.editScreen)
		{
			$('.remove_marker').removeClass('prototype');
		}
	};

	pro.attachSubmitCancelListener = function()
	{
		$('#ilInteractiveVideoCommentCancel').click(function()
		{
			pro.showButtons();
			pro.actual_marker = null;
		});

		$('#ilInteractiveVideoCommentSubmit').click(function()
		{
			pro.showButtons();
		});
	};

	pro.hideMakerToolBarObjectsForForm = function(prototype)
	{
		$('.marker_toolbar_element').removeClass('prototype');
		$.each(pri[prototype], function( index, value ) {
			$('.' + value).addClass('prototype');
		});
	};

	pro.attachStyleEvents = function()
	{
		$("#width_changer").on("input change", function() {
			pri.actual_marker.width($(this).val());
			pro.replaceFakeMarkerAfterAttributeChange();
		});

		$("#height_changer").on("input change", function() {
			pri.actual_marker.height($(this).val());
			pro.replaceFakeMarkerAfterAttributeChange();
		});

		$("#scale_changer").on("input change", function() {
			pri.actual_marker.scale($(this).val());
			pro.replaceFakeMarkerAfterAttributeChange();
		});

		$("#color_picker").on("input change", function() {
			pri.actual_marker.stroke({'color' : $(this).val()});
			pro.replaceFakeMarkerAfterAttributeChange();
		});

		$("#color_picker_fill").on("input change", function() {
			pri.actual_marker.fill({'color' : $(this).val()});
			pro.replaceFakeMarkerAfterAttributeChange();
		});

		$("#stroke_picker").on("input change", function() {
			var color = $('#' + pub.actual_id).attr('stroke');
			pri.actual_marker.attr('stroke-width' , $(this).val());
			pri.actual_marker.attr('stroke' , color);
			pro.replaceFakeMarkerAfterAttributeChange();
		});

		$("#rotate_changer").on("input change", function() {
			pri.actual_marker.rotate($(this).val());
			pro.replaceFakeMarkerAfterAttributeChange();
		});

		$("#btn_delete").on("click", function() {
			pri.actual_marker = null;
			pro.showButtons();
			$('.iv_svg_marker').remove();
			$('#fake_marker').html('');
			$('#add_marker_chk').prop('checked', false);
			$('.add_marker_selector').hide( 'fast' );
		});

	};

	pro.replaceFakeMarkerAfterAttributeChange = function()
	{
		$("#fake_marker").val($("#ilInteractiveVideoOverlay").html());
	};

	pro.createSvgElement = function(id, prototype_class)
	{
		if(prototype_class === 'rect_prototype')
		{
			pro.attachRectangle(id);
		}
		else if(prototype_class === 'circle_prototype')
		{
			pro.attachCircle();
		}
		else if(prototype_class === 'arrow_prototype')
		{
			pro.attachArrow();
		}
		else if(prototype_class === 'line_prototype')
		{
			pro.attachLine();
		}
	};

	pro.attachRectangle= function(id)
	{
		var draw = SVG('ilInteractiveVideoOverlay');
		var rect = draw.rect(100, 80);
		rect.stroke({ width: pro.stroke_width , color : pro.default_color});
		rect.fill('none');
		rect.attr('class', pro.marker_class);
		rect.attr('id', id);
		rect.draggable();
		pri.actual_marker = rect;
	};

	pro.attachCircle = function(id)
	{
		var draw = SVG('ilInteractiveVideoOverlay');
		var circle = draw.circle(100, 80);
		circle.stroke({ width: pro.stroke_width , color : pro.default_color});
		circle.fill('none');
		circle.scale(1, 0.9);
		circle.attr('class', pro.marker_class);
		circle.attr('id', id);
		circle.draggable();
		pri.actual_marker = circle;
	};

	pro.attachLine = function(id)
	{
		var draw = SVG('ilInteractiveVideoOverlay');
		var line = draw.line(0, 75, 150, 75);
		line.stroke({ width: pro.stroke_width , color : pro.default_color});
		line.fill('none');
		line.attr('class', pro.marker_class);
		line.attr('id', id);
		line.draggable();
		pri.actual_marker = line;
	};

	pro.attachArrow = function(id)
	{
		var draw = SVG('ilInteractiveVideoOverlay');
		var arrow = draw.path('m0,50l50,-50l50,50l-25,0l0,50l-50,0l0,-50l-25,0z');
		arrow.fill(pro.default_color);
		arrow.stroke({'width' : 0});
		arrow.attr('class', pro.marker_class);
		arrow.attr('id', id);
		arrow.draggable();
		pri.actual_marker = arrow;
	};

	pro.attachSingleObjectListener = function(button_id, prototype_class)
	{
		$('#' + button_id).off('click');

		$('#' + button_id).click(function()
		{
			pro.hideMakerToolBarObjectsForForm(prototype_class);
			if( ! pub.stillEditingSvg())
			{
				var id	= pro.getUniqueId();
				pro.createSvgElement(id, prototype_class);
				pro.removeButtons();
			}
		});
	};

	pro.getUniqueId = function()
	{
		var unique_id = '_' + Math.random().toString(36).substr(2, 9);
		pub.actual_id = unique_id;
		return unique_id;
	};

	pro.removeButtons = function()
	{
		$('.marker_button_toolbar').addClass('prototype');
		$('.marker_toolbar').removeClass('prototype');
		$('.remove_marker').removeClass('prototype');
	};

	pro.showButtons = function()
	{
		$('.marker_button_toolbar').removeClass('prototype');
		$('.marker_toolbar').addClass('prototype');
		$('.remove_marker').addClass('prototype');
	};

	pub.stillEditingSvg = function()
	{
		if(pub.actual_id === null)
		{
			pro.showButtons();
			return false;
		}
		else if($('#' + pub.actual_id).length > 0)
		{
			return true;
		}
		else if($('#' + pub.actual_id).length === 0)
		{
			pub.actual_id = null;
			pro.showButtons();
			return false;
		}
	};

	pub.protect = pro;
	return pub;

}(il));

var interval = setInterval(function() {
	if(document.readyState === 'complete') {
		clearInterval(interval);
		il.InteractiveVideoOverlayMarker.checkForEditScreen();
	}
}, 100);