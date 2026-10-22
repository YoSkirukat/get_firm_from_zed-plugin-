jQuery(document).ready(function($){
	var OldVal;
	var DataModified = false;

	$('.ButtApply').prop('disabled', true);

	$('body').on('dblclick', '.edit', function(){
		if($(this).hasClass('modify')) return;
		OldVal = $(this).html();
		$(this).toggleClass('modify');
		$(this).empty().append('<input type="text" class="edit_field" value="' + OldVal + '" />');
		$('.edit_field').focus();
		$('.edit_field').select();
		$('.edit_field').blur(function(){
			if(OldVal !== $(this).val())
			{
				DataModified = true;
				$(this).closest('tr').attr('id', 'modified');
				$('.ButtApply').prop('disabled', false);
			}
			$(this).parent().toggleClass('modify');
			$(this).parent().empty().html($(this).val());
		});
	});

	$('body').on('keydown', '.edit_field', function(event){
		if(event.keyCode == 13) $('.edit_field').blur();
		if(event.keyCode == 27)
		{
			$(this).val(OldVal);
			$('.edit_field').blur();
		}
	});

	$(window).bind('beforeunload', function(event){
		if(DataModified) return 'Изменённые данные не сохранены. Закрыть страницу?';
	});

	$('body').on('click', '.ButtApply', function(){
		var DB_Data = new Array();
		var Modified = $('[id = modified]');

		Modified.each(function(){
			var ServiceAccess = '0';
			var ID = $(this).children().eq(0).attr('id');
			var TrustLimit = $(this).children().eq(-2).html();
			var Rate = $(this).children().eq(-3).html();
			if($(this).children().eq(-4).children().prop('checked')) ServiceAccess = '1';
			DB_Data.push({'ID':ID, 'ServiceAccess':ServiceAccess, 'Rate':Rate, 'TrustLimit':TrustLimit});
		});

		var Data = {action: 'gffz_ModifyUsers', data: DB_Data};

		$.post(WP_Ajax.Url, Data, function(Res){
			if(Res == 'success')
			{
				$('.ButtApply').prop('disabled', true);
				$('[id = modified]').removeAttr('id');
				DataModified = false;
				alert('Операция выполнена. Изменено записей: ' + Modified.length);
			}
			else
				alert(Res);
		});
	});

	$('body').on('click', '.CB_Access', function(){
		DataModified = true;
		$(this).closest('tr').attr('id', 'modified');
		$('.ButtApply').prop('disabled', false);
		gffz_j001_ApplyFilter($);
	});

	$('body').on('dblclick', '.GetUserStat', function(){
		var ID = $(this).attr('id');

		$('#SendCMD').children().eq(1).attr('value', ID);
		$('#SendCMD').submit();
	});

	$('body').on('click', '#CB_U', function(){
		gffz_j001_ApplyFilter($);
	});

	$('body').on('click', '#CB_SU', function(){
		gffz_j001_ApplyFilter($);
	});
});

function gffz_j001_User($)
{
	var Select = $('.UserTbl input:not(:checked)');
	var Hide= ! $('#CB_U').prop('checked');

	if(Select.length)
		Select.closest('tr').prop('hidden', Hide);
}

function gffz_j001_ServiceUser($)
{
	var Select = $('.UserTbl input:checked');
	var Hide= ! $('#CB_SU').prop('checked');

	if(Select.length)
		Select.closest('tr').prop('hidden', Hide);
}

function gffz_j001_ApplyFilter($)
{
	gffz_j001_User($);
	gffz_j001_ServiceUser($);
}
