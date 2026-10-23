// Редактирование цен моделей (колонка Cost) в таблице Models List.
// Файл подключается только для администратора.

var gffz_ModelCostOldVal;
var gffz_ModelCostModified = false;

jQuery(document).ready(function($){
	var TheTable = $('table.MdlTbl');

	if(! TheTable.length || typeof gffz_ModelCost === 'undefined') return;

	var Header = TheTable.find('thead > tr:last');
	if(! Header.length)
	{
		var FirstRow = TheTable.find('tr:first');
		if(FirstRow.find('th').length) Header = FirstRow;
	}
	if(Header.length) Header.append('<th title="Стоимость прошивки">Cost</th>');

	var GetModel = function(Row){
		return $.trim($(Row).children().eq(1).html());
	};

	var GetArticle = function(Row){
		var Input = $(Row).find('input[name="MDL"]');
		return Input.length ? $.trim(Input.val()) : '';
	};

	var Articles = (typeof gffz_ModelCost.Articles === 'object' && gffz_ModelCost.Articles) ? gffz_ModelCost.Articles : {};
	var ArticlesSync = [];

	var GetCost = function(Cell){
		return $.trim($(Cell).text());
	};

	var SetDefault = function(Cell){
		Cell.html('<span class="MdlCostDefault" title="Стоимость по умолчанию">' + gffz_ModelCost.Default + '</span>');
	};

	TheTable.find('tbody > tr').each(function(){
		var Model = GetModel(this);
		var Cell;

		$(this).append('<td class="MdlCost edit"></td>');
		Cell = $(this).children().last();

		if(Object.prototype.hasOwnProperty.call(gffz_ModelCost.Costs, Model))
		{
			Cell.text(gffz_ModelCost.Costs[Model]);

			var Article = GetArticle(this);
			var Known = Object.prototype.hasOwnProperty.call(Articles, Model) ? Articles[Model] : '';

			if(Article !== '' && Known !== Article)
				ArticlesSync.push({'Model': Model, 'Article': Article, 'Cost': gffz_ModelCost.Costs[Model]});
		}
		else
			SetDefault(Cell);
	});

	// Однократная привязка артикулов к уже сохранённым ценам.
	if(ArticlesSync.length)
	{
		$.post(gffz_ModelCost.Url, {action: 'gffz_ModifyModels', data: ArticlesSync}, function(Res){
			if(Res != 'success') return;

			$.each(ArticlesSync, function(i, Item){ Articles[Item.Model] = Item.Article; });
			ArticlesSync = [];
		});
	}

	$('.ButtApplyCost').prop('disabled', true);

	$('body').on('dblclick', '.MdlCost', function(){
		var Cell = $(this);
		var OldHtml;

		if(Cell.hasClass('modify')) return;

		OldHtml = Cell.html();
		gffz_ModelCostOldVal = GetCost(Cell);
		Cell.toggleClass('modify');
		Cell.empty().append('<input type="text" class="MdlCostField" value="' + gffz_ModelCostOldVal + '" />');
		$('.MdlCostField').focus();
		$('.MdlCostField').select();
		$('.MdlCostField').blur(function(){
			var Val = $.trim($(this).val());

			if(gffz_ModelCostOldVal !== Val)
			{
				gffz_ModelCostModified = true;
				Cell.closest('tr').attr('id', 'modified');
				$('.ButtApplyCost').prop('disabled', false);
			}

			Cell.toggleClass('modify');
			if(gffz_ModelCostOldVal === Val)
				Cell.html(OldHtml);
			else
			{
				Cell.empty();
				if(Val === '') SetDefault(Cell); else Cell.text(Val);
			}
		});
	});

	$('body').on('keydown', '.MdlCostField', function(event){
		if(event.keyCode == 13) $('.MdlCostField').blur();
		if(event.keyCode == 27)
		{
			$(this).val(gffz_ModelCostOldVal);
			$('.MdlCostField').blur();
		}
	});

	$(window).bind('beforeunload', function(event){
		if(gffz_ModelCostModified) return 'Изменённые данные не сохранены. Закрыть страницу?';
	});

	$('body').on('click', '.ButtApplyCost', function(){
		var ModelCostData = [];
		var Modified = $('tr[id = modified]');

		Modified.each(function(){
			ModelCostData.push({'Model': GetModel(this), 'Article': GetArticle(this), 'Cost': GetCost($(this).children('.MdlCost'))});
		});

		$.post(gffz_ModelCost.Url, {action: 'gffz_ModifyModels', data: ModelCostData}, function(Res){
			if(Res == 'success')
			{
				$('.ButtApplyCost').prop('disabled', true);
				$('tr[id = modified]').each(function(){ Articles[GetModel(this)] = GetArticle(this); });
				$('tr[id = modified]').removeAttr('id');
				gffz_ModelCostModified = false;
				alert('Операция выполнена. Изменено записей: ' + Modified.length);
			}
			else
				alert('Сбой во время сохранения.');
		})
			.fail(function(){
				alert('Сбой во время сохранения.');
			});
	});
});
