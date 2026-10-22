var Summ;
var OldVal;
var gffz_FilterOldVal;
var DataModified = false;
var DataAdded = false;
var Remove = 0;

jQuery(document).ready(function($){
	gffz_ButtState($);
	gffz_Recalc($);
	gffz_ApplyFilter($);

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
				gffz_ButtState($);
			}
			$(this).parent().toggleClass('modify');
			$(this).parent().empty().html($(this).val());
			gffz_Recalc($);
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
		if(DataModified || Remove) return 'Изменённые данные не сохранены. Закрыть страницу?';
	});

	$('body').on('click', '.CB_Del', function(){
		if($(this).prop('checked')) Remove++; else Remove--;
		gffz_ButtState($);
	});

	$('body').on('click', '.ButtArch', function(){
		Remove = 0;
		var ID = $('.CB_Del:checked').closest('tr').children().eq(0).attr('id');
		$('#ArchStatCMD').children().eq(1).attr('value', ID);
		$('#ArchStatCMD').submit();
	});

	$('body').on('click', '.ButtApply', function(){
		var PostRes;
		var RemoveCount;
		var RemoveRows;
		var ModifyCount;
		var ModifyRows;
		var ModifySuccess;
		var NewID;

		var DoPost = function(Data){
			var dfd = $.Deferred();

			$.post(WP_Ajax.Url, Data, function(Res){
				if(Res.substr(0, 7) == 'success')
				{
					PostRes = true;
					NewID = Res.substr(7);
					dfd.resolve();
				}
				else
				{
					PostRes = false;
					dfd.reject();
				}
			})
				.fail(function(){PostRes = false; dfd.reject();});

			return dfd.promise();
		};

		var DoRemove = function(Index){
			var dfd = $.Deferred();
			var ID;
			var Data;

			if(Index < RemoveCount)
			{
				ID = $(RemoveRows[Index]).closest('tr').children().eq(0).attr('id');
				Data = {action: 'gffz_RemoveStat', ID: ID};
				$.when(DoPost(Data)).always(function(){
					if(PostRes)
					{
						Remove--;
						$(RemoveRows[Index]).closest('tr').remove();
					}

					Index++;
					if(Index == RemoveCount)
					{
						if(Remove) alert('Сбой во время удаления.'); else alert('Удаление выполнено.');
						dfd.resolve();
					}
					else
						$.when(DoRemove(Index)).always(function(){
							dfd.resolve();
						});
				});
			}
			else
				dfd.reject();

			return dfd.promise();
		};

		var DoModify = function(Index){
			var dfd = $.Deferred();
			var ID;
			var ID1;
			var Model;
			var SN;
			var CRUM;
			var AdminCost;
			var Data;
			var Row;

			if(Index < ModifyCount)
			{
				Row = ModifyRows[Index];
				ID = $(Row).children().eq(0).attr('id');
				ID1 = $(Row).children().eq(-1).attr('id');
				Model = $(Row).children().eq(3).html();
				SN = $(Row).children().eq(4).html();
				CRUM = $(Row).children().eq(5).html();
				AdminCost = $(Row).children().eq(7).html();
				Data = {action: 'gffz_ModifyStat', ID: ID, ID1: ID1, Model: Model, SN: SN, CRUM: CRUM, AdminCost: AdminCost};
				$.when(DoPost(Data)).always(function(){
					if(PostRes)
					{
						ModifySuccess++;
						$(Row).removeAttr('id');
						$(Row).children().eq(0).attr('id', NewID);
						$(Row).children().eq(-1).attr('id', NewID);
					}

					Index++;
					if(Index == ModifyCount)
					{
						if(ModifySuccess == ModifyCount)
						{
							DataModified = false;
							alert('Изменения выполнены.');
						}
						else
							alert('Сбой во время изменения.');

						dfd.resolve();
					}
					else
						$.when(DoModify(Index)).always(function(){
							dfd.resolve();
						});
				});
			}
			else
				dfd.reject();

			return dfd.promise();
		};

		var DoApply = function(){
			var dfd = $.Deferred();

			if(Remove)
			{
				RemoveRows = $('.CB_Del:checked');
				RemoveCount = RemoveRows.length;
			}
			else
				RemoveCount = 0;

			$.when(DoRemove(0)).always(function(){
				if(DataModified)
				{
					ModifyRows = $('[id = modified]');
					ModifyCount = ModifyRows.length;
					ModifySuccess = 0;
				}
				else
					ModifyCount = 0;

				$.when(DoModify(0)).always(function(){
					dfd.resolve();
				});
			});

			return dfd.promise();
		};

		$.when(DoApply()).always(function(){
			gffz_Recalc($);
			gffz_ButtState($);
		});
	});

	$('body').on('click', '.ButtAdd', function(){
		var ID;
		var ID1;

		ID = $(this).closest('td').attr('id');
		if(ID == 'last')
			ID1 = 0;
		else
			ID1 = $(this).closest('tr').children().eq(-1).attr('id');

		$(this).closest('tr').before('<tr><td id="0"><input type="button" class="ButtAdd" value="^" />&nbsp;<input type="checkbox" class="CB_Del" /></td><td id="1">N/A</td><td>N/A</td><td class="edit">Модель</td><td class="edit">S/N</td><td class="edit">CRUM</td><td>N/A</td><td class="edit" style="text-align: right;">0</td><td id="' + ID1 + '" style="text-align: right;"></td></tr>');
		gffz_Recalc($);
	});

	$('body').on('dblclick', '.GetUserStat', function(){
		var ID = $(this).attr('id');

		$('#GetUserStatCMD').children().eq(1).attr('value', ID);
		$('#GetUserStatCMD').submit();
	});

	$('#gffz_Filter').blur(function(){
		gffz_ApplyFilter($);
	});

	$('#gffz_Filter').focus(function(){
		gffz_FilterOldVal = $(this).val();
		$(this).select();
	});

	$('body').on('keydown', '#gffz_Filter', function(event){
		if(event.keyCode == 13) $('#gffz_Filter').blur();
		if(event.keyCode == 27)
		{
			$(this).val(gffz_FilterOldVal);
			$('#gffz_Filter').blur();
		}
	});
});

function gffz_Recalc($)
{
	var Rows;
	var RowsCount;
	var Cost;

	Rows = $('.CommStatTbl').find('tr');
	RowsCount = Rows.length;
	if(RowsCount > 2)
	{
		Summ = parseFloat($('#gffz_BeginBalans').val());
		--RowsCount;

		for(i = 1; i < RowsCount; ++i)
		{
			Cost = parseFloat($(Rows[i]).children().eq(-2).html());
			Summ += Cost;
			$(Rows[i]).children().eq(-1).html(parseFloat(Summ.toFixed(3)));
		}
	}
}

function gffz_ButtState($)
{
	if(DataModified || Remove) State = false; else State = true;
	$('.ButtApply').prop('disabled', State);

	if(Remove == 1) State = false; else State = true;
	$('.ButtArch').prop('disabled', State);
}

function gffz_ApplyFilter($)
{
	var Select = $('.CommStatTbl tr');
	var SelectCount = Select.length - 2;
	var DisplayCount = parseInt($('#gffz_Filter').val(), 10);
	var LastCount;

	if(DisplayCount > SelectCount)
		LastCount = SelectCount;
	else
		LastCount = DisplayCount;

	Select.slice(1, SelectCount - LastCount + 1).prop('hidden', true);
	Select.slice(SelectCount - LastCount + 1, SelectCount + 1).prop('hidden', false);
}
