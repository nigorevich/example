<?php

class payments extends def{
	const TRANSACTION_FILE_MAX_LINES = 10000;
	const MAX_TRANSFER_PAYMENT_ATTEMPTS = 30;
	const MIN_TRANSFER_PAYMENT_ATTEMPTS = 1;
	const MASS_CANCELLATION_CSV_MAX_LINES = 10000;
	const MASS_CANCELLATION_ALLOWED_TRANSFER_TYPES = [30, 32];

	function __construct(){

	}
	function view(){
		$this->saveManagerHistory("v4/payments");
		$o = array();
		$o['tpl'] = self::defaultTpl();
		return $o;
	}

	function defaultTpl(){
		$tpl = def::loadTpl('payments');
		$data = array();

		$filters = array();
		$filters[] = $tpl['filters']['rent_id'];
		$filters[] = $tpl['filters']['order_id'];
		$filters[] = $tpl['filters']['card_number'];
		$filters[] = $tpl['filters']['period'];
		$filters[] = $tpl['filters']['statuses'];


		$filters[] = self::getTypes();
		$filters[] = self::getBanks();

		if(def::checkPermission('clients','view')){
			$filters[] = def::loadTpl('clients')['fastsearch'];
		}
		
		$data['filters'] = implode('', $filters);


		$data['menu'] = $this->getMenu();

		$export = array();
		if(def::checkPermission('export','payments')){
			$export[] = $tpl['export'];
		}
		
		$data['export'] = implode('', $export);

		return def::parseTpl($tpl['default'],$data);
	}

	function getTypes(){
		$tpl = def::loadTpl('payments');
		$data = array();
		$data['list'] = array();
		$resp = def::query("SELECT id,name FROM transfer_type");
		if($resp && $resp -> num_rows){
			while($r=$resp->fetch_assoc()){
				$data['list'][] = '<option value="'.$r['id'].'">'.$r['name'].'</option>';
			}
			$resp -> free();
		}
		$data['list'] = implode('', $data['list']);
		return def::parseTpl($tpl['filters']['types'],$data);
	}


	function page($page=1){
		$this->saveManagerHistory("v4/payments/page/$page", ((int) def::getRequest('client_id')) ?? null);
		$o = array();

		$page = $page === '' ? 1 : $page;
		$per_page = 15;
		$offset = ($page - 1) * $per_page;

		$filter = array();
        $joins = [];
        $selects = [];

        $start_from = def::getRequest('start_from');
		$start_to = def::getRequest('start_to');
		if($start_from || $start_to){
			$start_time = array();
			if($start_from){
				$start_from = def::format('utc_time',$start_from);
				$start_time[] = "pay.timestamp>='$start_from'";
			}
			if($start_to){
				$start_to = def::format('utc_time',$start_to);
				$start_time[] = "pay.timestamp<='$start_to'";
			}
			
			$filter[] = implode(' AND ', $start_time);
		}

		$client_id = def::getRequest('client_id');
		if($client_id){
			$filter[] = "pay.user_id=$client_id";
		}

		$rent_id = def::getRequest('rent_id');
		if($rent_id){
			$filter[] = "pay.rent_id=$rent_id";
		}

		$order_id = def::getRequest('order_id');
		if($order_id){
			$filter[] = "pay.order_id=$order_id";
		}

		$card_number = def::getRequest('card_number');
		if($card_number){
			$filter[] = "pay.card = '$card_number'";
		}

		$status = def::getRequest('status');
		if($status){
			$filter[] = "pay.status='$status'";
		}

		$type = def::getRequest('type');
		if($type){
			$filter[] = "pay.type=$type";
		}

		$bank = def::getRequest('bank');
		if($bank){
			$filter[] = "pay.bank=$bank";
		}

        $penaltyArticleShow = (int)$this->getRequest('penaltyArticleShow');

        if($penaltyArticleShow){
            $joins[] = "LEFT JOIN penalties ON pay.penalty_id = penalties.id";
            $selects[] = "penalties.article";
        }

        $penaltyArticle = addslashes(trim($this->getRequest('penaltyArticle')));

        if($penaltyArticle){
            if(!$penaltyArticleShow)
            {
                $joins[] = "LEFT JOIN penalties ON pay.penalty_id = penalties.id";
                $selects[] = "penalties.article";
            }

            $filter[] = "penalties.article LIKE '%$penaltyArticle%'";
        }

        if(count($filter)){
			$filter = "WHERE ".implode(' AND ', $filter);
		} else {
			$filter = '';
		}
		$o['filter'] = $filter;

        if(count($joins)){
            $joins = implode(' ', $joins);
        } else {
            $joins = '';
        }

        if(count($selects)){
            $selects = ','. implode(', ', $selects);
        } else {
            $selects = '';
        }


        $resp = def::querySlave("query $selects 
			FROM ...
			$joins 
			$filter
			ORDER BY pay.id DESC
			LIMIT $per_page
			OFFSET $offset
");

		if($resp && $resp -> num_rows){
			$client_object = def::checkPermission('clients','object');
			$user_object = def::checkPermission('users','object');
			$refresh_perm = def::checkPermission('payments','refresh');
			$payment_object = def::checkPermission('payments','object');
			$statuses = array();
			$statuses['error'] = 'Ошибка';
			$statuses['success'] = 'Успешно';
			$statuses['waiting'] = 'Ожидание';
			$statuses['authorized'] = 'Заблокировано';
			$statuses['unlocked'] = 'Разблокировано';
			$statuses['canceled'] = 'Отменено';
			$statuses['approved'] = 'Подтверждено';
			$statuses['refunded'] = 'Возвращено';

			while($r=$resp->fetch_assoc()){
				$item = array();
				$item['id'] = $r['id'];
				$item['order_id'] = ($r['order_id'])?$r['order_id']:'-';

				if($payment_object){
					$item['link'] = 'payments/object/'.$r['id'].'/info/';
				}

				$item['cn'] = $r['status'].' type'.$r['type'];

				$item['time'] = def::format('local_time',$r['timestamp']);
				$item['amount'] = $r['amount'];
				$item['bonus'] = $r['bonus'];
				$item['status'] = $statuses[$r['status']] ?? '';
				
				$item['type'] = $r['type'];
				$item['type_name'] = $r['type_name'];

				$item['bank_name'] = $r['description'];

				$item['client'] = array();
				$item['client']['id'] = $r['user_id'];
				$client_index = '';
				$item['client']['name'] = $r['last_name'].' '.$r['first_name'].$client_index;
				$item['client']['type'] = $r['client_type'];
				if($client_object){
					$item['client']['link'] = 'clients/object/'.$r['user_id'].'/info/';
				}

				if($r['manager_id']){
					$item['user'] = array();
					$item['user']['name'] = '('.$r['manager_id'].') '.$r['manager_name'];
					if($user_object){
						$item['user']['link'] = 'users/object/'.$r['manager_id'].'/info/';
					}
				}

				$item['message'] = ($r['message'])?$r['message']:'-';

				$refresh = ($refresh_perm && ($r['status'] == 'waiting' || $r['status'] == 'error') && $r['bank'] == 34 && (time() - strtotime($r['timestamp']))/60 > 5 );
				$function = ($refresh)?'<div class="refresh" data-id="'.$r['id'].'"><div class="i"></div><div class="n">Обновить</div></div>':'';
				$item['functions'] = $function;

				$item['card'] = ($r['card'])?$r['card']:'-';
                $item['article'] =  $r['article'] ?? '';

                $o['list'][] = $item;
			}
			$resp -> free();
		}

		$o['payment_count'] = count($o['list'] ?? []);
		$o['current_page'] = $page;
		$o['per_page'] = $per_page;

		return $o;
	}

	function getObjectMenu($object=false,$section=false){
		$menu = array();

		if($object){
			$object_id = $object['id'];
		} else {
			$object_id = '%object_id%';
		}

		if(def::checkPermission('cars','route')){
			$menu['info'] = '<div data-id="'.$object_id.'" class="route%active%"><span class="i"></span><span class="n">Маршрут</span></div>';
		}

		foreach($menu as $k=>$v){
			$cn = ($section==$k)?' active':'';
			$menu[$k] = preg_replace('/%active%/', $cn, $v);
		}
		return implode('', $menu);
	}


	function object($object_id=false,$section=false){
		$o = false;
		if(!$section){
			$section = 'info';
		}
		if($object_id){
			$tpl = def::loadTpl('payments');
			$o = ['section'=>$section];
			$data = array(
				'object_id' => $object_id,
				'order_id' => false,
				'h1' => 'Платеж ID: '.$object_id,
				'object_link' => 'payments/object/'.$object_id.'/info/',
				'section_link' => 'payments/object/'.$object_id.'/'.$section.'/',
				'section' => $section,
				'menu' => false
			);

			$resp = def::select("SELECT select
				FROM transfer as pay
				LEFT JOIN clients as client ON client.id=pay.user_id {$this->getClientFilterSubQuery()}
				LEFT JOIN client_types as client_type ON client_type.id=client.type
			    ...
				WHERE pay.id=$object_id");
			if($resp && $resp -> num_rows){
				$object = $resp -> fetch_assoc();
				$resp -> free();
				$this->saveManagerHistory("v4/payments/object/$object_id/$section", (int) $object['client_id']);

				if(isset($tpl['object'][$section])){
					if($object['order_id']){
						$data['h1'] = 'Платеж OrderID: '.$object['order_id'];
						$data['order_id'] = $object['order_id'];
					}
					$data['name'] = $object['id'];

					$data = array_merge($data,self::getObjectData($object,$section));

					$o['tpl'] = def::parseTpl($tpl['object'][$section],$data);
				} else {
					$o['tpl'] = def::parseTpl($tpl['object']['denied'],$data);
				}
			} else {
				$o['tpl'] = def::parseTpl($tpl['object']['not_found'],$data);
			}
		}

		return $o;
	}

    private function downloadFile($filePath, $fileName)
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/docx');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);

        @unlink($filePath);

        exit;
    }
}
