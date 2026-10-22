jQuery(document).ready(function($){
	$('#SendForm').submit(function(e){
		var MdlName;
		var Model;
		var SN;
		var CRUM;
		var Balans;
		var DoRequest = true;

		$('#ButtSend').prop('disabled', true);
		e.preventDefault();

		var Checked = $('.MdlTbl').find("input:checked");
		if(Checked.length == 0)
		{
			DoRequest = false;
			alert('Модель не выбрана.');
		}

		SN = $('#SN').val();
		if(SN.length != 10 && SN.length != 15)
		{
			DoRequest = false;
			alert('Machine serail number должен содержать 10 или 15 знаков.');
		}

		if(DoRequest)
		{
			MdlName = $.trim(Checked.closest('tr').children().eq(1).html());
			Model = Checked.val();
			CRUM = $('#CRUM').val();

			var Data = {'action': 'gffz_SendRequest', 'BRAND':'Samsung', 'SN':SN, 'CRM':CRUM, 'MDL':Model, 'MDLNAME':MdlName};

			$.post(WP_Ajax.Url, Data, function(Ret){
				var Res = JSON.parse(Ret);

				if(Res.Result)
				{
					Balans = parseFloat($('#Balans').html());
					Balans += Res.Cost;
					$('#Balans').html(Balans);
					alert('Операция выполнена.');
				}
				else
					alert(Res.Msg);

				$('#ButtSend').prop('disabled', false);
			});
		}
		else
		{
			$('#ButtSend').prop('disabled', false);
		}
	});

	$('#SN').blur(function(){
		$(this).val($(this).val().toUpperCase());
	});
});
