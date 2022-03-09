modules['payments'] = payments = {
	body: null,
	post: null,
	result: null,

	page: function(resp){
		if(typeof(resp)!='undefined'){
			payments.result = resp;
		}
		if(!$('#payments')[0]){
			app.request('payments/');
		} else if(payments.result){
			if(payments.result.status == 'error' || payments.result.status == 'denied'){
				$('#result>.data').html(payments.result);
			} else if(payments.result.payment_count>=0){
				var result = '<div class="notfound">К сожалению, поиск не дал результатов.</div>';
				if(payments.result.payment_count>0){
					result = payments.list(payments.result.list);
				}
				$('#result>.data').html(result);
				$('#result>.data a').on('click',app.catchLink);
				if(payments.refresh){
					$('#result>.data>table>tbody>tr>td>.functions>.refresh').on('click',payments.refresh.on);
				}
				var paymentResult = payments.result;
				var pager = ui.singlePage(paymentResult.current_page, 'payments', paymentResult.payment_count, paymentResult.per_page);
				$('#result>.pager').html(pager);
				$('#result>.pager>a').on('click',app.catchLink);
			}
			payments.body.removeClass('loading');
			payments.result = null;
		} else {
			app.request('payments/page/');
		}
	}

};
