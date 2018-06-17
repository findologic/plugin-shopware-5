<?php

class Shopware_Controllers_Api_Findologic extends Shopware_Controllers_Api_Rest
{

    public function indexAction() {
        $this->View()->assign(['success' => true]);
    }

}