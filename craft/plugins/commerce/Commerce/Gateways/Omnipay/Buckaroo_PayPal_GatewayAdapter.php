<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Buckaroo_PayPal_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Buckaroo_PayPal';
    }
}