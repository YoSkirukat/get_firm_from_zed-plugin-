jQuery(document).ready(function($){
	$('#SendForm').submit(function(e){
		var Balans;

		$('#ButtSend').prop('disabled', true);
		e.preventDefault();

		var Data = {'action': 'gffz_SendRequest', 'BRAND':'Epson', 'REQUESTDATA':$('#ReqData').val()};

		$.post(WP_Ajax.Url, Data, function(Ret){
			var Res = JSON.parse(Ret);

			if(Res.Result)
			{
				Balans = parseFloat($('#Balans').html());
				Balans += Res.Cost;
				$('#Balans').html(Balans);
				$('#Ticket').val(Res.Msg);
				alert('Операция выполнена.');
			}
			else
				alert(Res.Msg);

			$('#ButtSend').prop('disabled', false);
		});
	});
});
