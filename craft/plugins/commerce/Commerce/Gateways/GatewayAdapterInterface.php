<?php
namespace Commerce\Gateways;

use Craft\Commerce_PaymentMethodModel;

/**
 * Interface GatewayAdapterInterface
 * @package Commerce\Gateways
 *
 * @method protected array defineAttributes() Use it to define setting parameters, it's labels and rules. Must be protected
 */
interface GatewayAdapterInterface
{
	/**
	 * @return Commerce_PaymentMethodModel|null
	 */
	public function getPaymentMethod();

	/**
	 * @param Commerce_PaymentMethodModel $paymentMethod
	 */
	public function setPaymentMethod(Commerce_PaymentMethodModel $paymentMethod);

    /** @return string */
    public function handle();

    /** @return string */
    public function displayName();

    /** @return string */
    public function getSettingsHtml();

    /** @return bool */
    public function requiresCreditCard();
}