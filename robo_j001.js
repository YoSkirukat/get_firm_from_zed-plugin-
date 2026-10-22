jQuery(document).ready(function($){
	$('body').on('click', '#gffz_ButtSubmit', function(){
		var Summ = $('#gffz_RoboSumm').val();
		var Sign;

		if(Summ < 1)
			alert('Сумма не введена.');
		else
		{
			var Data = {'action':'gffz_RoboCalcSign', 'Summ':Summ};

			$.post(WP_Ajax.Url, Data, function(Res){
				if(Res.substr(0, 7) == 'success')
				{
					Sign = Res.substr(7);
					$('#gffz_RoboSign').attr('value', Sign);
					$('#gffz_RoboOutSumm').attr('value', Summ);
					$('#gffz_RoboShpSumm').attr('value', Summ);
					$('#gffz_RoboSendReq').submit();
				}
				else
					alert('Сбой операции.');
			});
		}
	});
});
