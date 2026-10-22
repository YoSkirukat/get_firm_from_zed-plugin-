
var gffz_FilterOldVal;

jQuery(document).ready(function($){

	$('#gffz_Filter').blur(function(){
		var Select = $('.UserStatTbl tr');
		var SelectCount = Select.length - 1;
		var DisplayCount = parseInt($(this).val(), 10);
		var LastCount;

		if(DisplayCount > SelectCount)
			LastCount = SelectCount;
		else
			LastCount = DisplayCount;

		Select.slice(1, SelectCount - LastCount + 1).prop('hidden', true);
		Select.slice(SelectCount - LastCount + 1, SelectCount + 1).prop('hidden', false);
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
