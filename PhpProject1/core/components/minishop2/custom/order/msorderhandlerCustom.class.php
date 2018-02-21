<?php
if(!class_exists('msOrderInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/msorderhandler.class.php';
}

class artlOrderHandler extends msOrderHandler implements msOrderInterface {
	
	public function getCost($with_cart = true, $only_cost = false)    {
        $response = $this->ms2->invokeEvent('msOnBeforeGetOrderCost', array(
            'order' => $this,
            'cart' => $this->ms2->cart,
            'with_cart' => $with_cart,
            'only_cost' => $only_cost,
        ));
        if (!$response['success']) {
            return $this->error($response['message']);
        }

        $cart = $this->ms2->cart->status();
        $cost = $with_cart
            ? $cart['total_cost']
            : 0;

        /** @var msDelivery $delivery */
        if (!empty($this->order['delivery']) && $delivery = $this->modx->getObject('msDelivery', $this->order['delivery'])) {
            $deliveryData = $delivery->getCost($this, $cost);
            
            if($deliveryData['cost'] == -1) {
                return $this->error($deliveryData['message'], array('requires' => $deliveryData['requires']));
            }
                
            $cost = $deliveryData['cost'];
        }

        /** @var msPayment $payment */
        if (!empty($this->order['payment']) && $payment = $this->modx->getObject('msPayment',
                $this->order['payment'])
        ) {
            $cost = $payment->getCost($this, $cost);
        }

        $response = $this->ms2->invokeEvent('msOnGetOrderCost', array(
            'order' => $this,
            'cart' => $this->ms2->cart,
            'with_cart' => $with_cart,
            'only_cost' => $only_cost,
            'cost' => $cost,
        ));
        if (!$response['success']) {
            return $this->error($response['message']);
        }
        $cost = $response['data']['cost'];

        return $only_cost
            ? $cost
            : $this->success('', $deliveryData);
    }
	
}