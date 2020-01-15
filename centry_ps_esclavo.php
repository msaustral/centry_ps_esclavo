<?php

require_once(dirname(__FILE__) . '/vendor/autoload.php');

if (!defined('_PS_VERSION_')) {
  exit;
}

class Centry_PS_esclavo extends Module {

  public function __construct() {
    $this->name = 'centry_ps_esclavo';
    $this->tab = 'market_place';
    $this->version = '1.0.0';
    $this->author = 'Centry';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = [
      'min' => '1.7.4', // Upgrade Symfony to 3.4 LTS https://assets.prestashop2.com/es/system/files/ps_releases/changelog_1.7.4.0.txt
      'max' => _PS_VERSION_
    ];
    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('Centry Esclavo');
    $this->description = $this->l('Modulo que funciona como esclavo para Centry.');

    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    if (!Configuration::get('MYMODULE_NAME')) {
      $this->warning = $this->l('No name provided');
    }
  }

  public function install() {
    if (Shop::isFeatureActive()) {
      Shop::setContext(Shop::CONTEXT_ALL);
    }

    if (!parent::install() ||
            !$this->whenInstall("\\ProductCentry", "createTable") ||
            !$this->whenInstall("\\CategoryCentry", "createTable") ||
            !$this->whenInstall("\\ColorCentry", "createTable") ||
            !$this->whenInstall("\\SizeCentry", "createTable") ||
            !$this->whenInstall("\\FeatureValueCentry", "createTable") ||
            !$this->whenInstall("\\WebhookCentry", "createTable") ||
            !$this->whenInstall("\\BrandCentry", "createTable") ||
            !$this->whenInstall("\\VariantCentry", "createTable") ||
            !$this->whenInstall("\\AttributeGroupCentry", "createTable") ||
            !$this->whenInstall("\\FeatureCentry", "createTable") ||
            !$this->whenInstall("\\ImageCentry", "createTable") ||
            !$this->registerHook('leftColumn') ||
            !$this->registerHook('header') ||
            !$this->registerHook('actionValidateOrder') ||
            !$this->registerHook('actionOrderHistoryAddAfter')
    ) {
      return false;
    }

    return true;
  }

  private function whenInstall($class, $method) {
    if (!method_exists($class, $method)) {
      $this->_errors[] = Tools::displayError(sprintf(
                              $this->l('There is no method %1$s in class %2$s. This is happening because the module has no write permission to override the default prestashop classes. Contact your webmaster to fix this problem.')
                              , $method
                              , $class), false);
      return false;
    } elseif (!$class::$method()) {
      $this->_errors[] = Tools::displayError(sprintf($this->l('There was an error calling the %1$s\'s %2$s method.'), $class, $method), false);
      return false;
    }
    return true;
  }

  public function uninstall() {
    if (!parent::uninstall() ||
            !Configuration::deleteByName('MYMODULE_NAME')
    ) {
      return false;
    }

    return true;
  }

  public function hookactionValidateOrder($params) {
    //TODO: encolar notificacion, todo el seteo de info de la orden se va al controlador
    // error_log(print_r("hookactionValidateOrder", true));
    // error_log(print_r($params, true));
  }

  public function hookactionOrderHistoryAddAfter($params) {
    //TODO: encolar notificacion, todo el seteo de info de la orden se va al controlador
    // error_log(print_r("hookactionOrderHistoryAddAfter", true));
    // error_log(print_r($params, true));
  }

  public function getContent() {
    $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
    $output = null;
    $fields = ['name', 'price', 'priceoffer', 'description', 'skuproduct', 'characteristics', 'warranty', 'condition', 'status',
      'stock', 'variantsku', 'size', 'color', 'barcode', 'productimages', 'seo', 'brand', 'package', 'category'];

    if (Tools::isSubmit('submit' . $this->name)) {
      $centryAppId = strval(Tools::getValue('centryAppId'));
      $centrySecretId = strval(Tools::getValue('centrySecretId'));
      $name_value = Tools::getAllValues();
      foreach ($fields as $field) {
        $value_field_create = Tools::getValue("ONCREATE_" . $field);
        $value_field_update = Tools::getValue("ONUPDATE_" . $field);
        Configuration::updateValue('CENTRY_SYNC_ONCREATE_' . $field, $value_field_create);
        Configuration::updateValue('CENTRY_SYNC_ONUPDATE_' . $field, $value_field_update);
      }

      $price_behavior = Tools::getValue("price_behavior");
      Configuration::updateValue('CENTRY_SYNC_price_behavior', $price_behavior);

      $variant_simple = Tools::getValue("VARIANT_SIMPLE");
      Configuration::updateValue('CENTRY_SYNC_VARIANT_SIMPLE', $variant_simple);

      if (!$centryAppId || empty($centryAppId)) {
        $output .= $this->displayError($this->l('Invalid Centry App Id'));
      } else {
        Configuration::updateValue('CENTRY_SYNC_APP_ID', $centryAppId);
        $output .= $this->displayConfirmation($this->l('Centry App Id updated'));
      }
      if (!$centrySecretId || empty($centrySecretId)) {
        $output .= $this->displayError($this->l('Invalid Centry Secret Id'));
      } else {
        Configuration::updateValue('CENTRY_SYNC_SECRET_ID', $centrySecretId);
        $output .= $this->displayConfirmation($this->l('Centry Secret Id updated'));
      }

      foreach (OrderState::getOrderStates($defaultLang) as $state) {
        $status = new OrderStatusCentry($state['id_order_state'], Tools::getValue($this->l($state['id_order_state'])));
        $status->save();
      }
    }

    return $output . $this->displayForm();
  }

  public function displayForm() {
    // Get default language
    $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

    $statusFields = array();
    $sync_fields = [["id" => "name", 'name' => "Nombre"], ["id" => "price", 'name' => "Precio"], ["id" => "priceoffer", 'name' => "Precio de oferta"],
      ["id" => "description", 'name' => "Descripción"], ["id" => "skuproduct", 'name' => "Sku del Producto"], ["id" => "characteristics", 'name' => "Características"],
      ["id" => "stock", 'name' => "Stock"], ["id" => "variantsku", 'name' => "Sku de la Variante"], ["id" => "size", 'name' => "Talla"],
      ["id" => "color", 'name' => "Color"], ["id" => "barcode", 'name' => "Código de barras"], ["id" => "productimages", 'name' => "Imágenes Producto"],
      ["id" => "condition", 'name' => "Condición"], ["id" => "warranty", 'name' => "Garantía"], ["id" => "status", 'name' => "Estado"],
      ["id" => "seo", 'name' => "Campos SEO"], ["id" => "brand", 'name' => "Marca"], ["id" => "package", 'name' => "Medidas del paquete"],
      ["id" => "category", 'name' => "Categoría"]];


    // Init Fields form array
    $fieldsForm[0]['form'] = array(
      'legend' => array(
        'title' => $this->l('Settings'),
      ),
      'input' => array(
        array(
          'type' => 'text',
          'label' => $this->l('Centry App Id'),
          'name' => 'centryAppId',
          'required' => true
        ),
        array(
          'type' => 'text',
          'label' => $this->l('Centry Secret Id'),
          'name' => 'centrySecretId',
          'required' => true
        )
      ),
      'submit' => array(
        'title' => $this->l('Save'),
        'class' => 'btn btn-default pull-right'
      )
    );

    $fieldsForm[1]['form'] = array(
      'legend' => array(
        'title' => $this->l('Synchronization Fields'),
      ),
      'input' => array(
        array(
          'type' => 'checkbox',
          'label' => $this->l('Creación'),
          'name' => 'ONCREATE',
          'values' => array(
            'query' => array(
            ),
            'id' => 'id_option',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'checkbox',
          'label' => $this->l('Actualización'),
          'name' => 'ONUPDATE',
          'values' => array(
            'query' => array(
            ),
            'id' => 'id_option',
            'name' => 'name'
          )
        ),
        array(
          'type' => 'select',
          'label' => $this->l('Comportamiento Precio oferta'),
          'name' => 'price_behavior',
          'options' => array(
            'query' => array(
              array(
                'id_option' => 'percentage',
                'name' => 'Descuento en Porcentaje',
              ),
              array(
                'id_option' => 'discount',
                'name' => 'Descuento en precio'
              ),
              array(
                'id_option' => 'reduced',
                'name' => 'Reemplazar precio normal'
              )
            ),
            'id' => 'id_option',
            'name' => 'name',
          ),
        ),
        array(
          'type' => 'checkbox',
          'label' => $this->l('Crear productos con variante única como productos simples'),
          'name' => 'VARIANT',
          'values' => array(
            'query' => array(
              array(
                'id_option' => $this->l("SIMPLE"),
                'name' => $this->l("")
              )
            ),
            'id' => 'id_option',
            'name' => 'name'
          )
        )
      ),
      'submit' => array(
        'title' => $this->l('Save'),
        'class' => 'btn btn-default pull-right'
      )
    );

    foreach ($sync_fields as $sync_field) {
      $option = array(
        'id_option' => $this->l($sync_field['id']),
        'name' => $this->l($sync_field['name'])
      );
      array_push($fieldsForm[1]['form']['input'][0]['values']['query'], $option);
      array_push($fieldsForm[1]['form']['input'][1]['values']['query'], $option);
    }

    // Se insertan las homologaciones de estado al formulario
    $centryOptions = array();

    $fieldsForm[2]['form'] = array(
      'legend' => array(
        'title' => $this->l('Order States'),
      ),
      'submit' => array(
        'title' => $this->l('Save'),
        'class' => 'btn btn-default pull-right'
      )
    );
    foreach (OrderState::getOrderStates($defaultLang) as $state) {
      $fieldsForm[2]['form']['input'][] = array(
        'type' => 'select',
        'label' => $this->l($state["name"]),
        'name' => $this->l($state["id_order_state"]),
        'id' => $this->l($state["id_order_state"]),
        'options' => array(
          'id' => 'id_option',
          'name' => 'name',
          'query' => array(
            array(
              'id_option' => 1,
              'name' => 'pending',
            ),
            array(
              'id_option' => 2,
              'name' => 'shipped'
            ),
            array(
              'id_option' => 3,
              'name' => 'recieved'
            ),
            array(
              'id_option' => 4,
              'name' => 'cancelled'
            ),
            array(
              'id_option' => 5,
              'name' => 'cancelled before shipping'
            ),
            array(
              'id_option' => 6,
              'name' => 'cancelled after shipping'
            ),
          )
        ),
        'required' => true,
      );
    }
    $helper = new HelperForm();

    // Module, token and currentIndex
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

    // Language
    $helper->default_form_language = $defaultLang;
    $helper->allow_employee_form_lang = $defaultLang;

    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true;        // false -> remove toolbar
    $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit' . $this->name;
    $helper->toolbar_btn = [
      'save' => [
        'desc' => $this->l('Save'),
        'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
        '&token=' . Tools::getAdminTokenLite('AdminModules'),
      ],
      'back' => [
        'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->l('Back to list')
      ]
    ];

    // Load current value
    $helper->fields_value['centryAppId'] = Configuration::get('CENTRY_SYNC_APP_ID');
    $helper->fields_value['centrySecretId'] = Configuration::get('CENTRY_SYNC_SECRET_ID');
    foreach ($sync_fields as $sync_field) {
      $helper->fields_value['ONCREATE_' . $sync_field['id']] = Configuration::get('CENTRY_SYNC_ONCREATE_' . $sync_field['id']);
      $helper->fields_value['ONUPDATE_' . $sync_field['id']] = Configuration::get('CENTRY_SYNC_ONUPDATE_' . $sync_field['id']);
    }
    $helper->fields_value['price_behavior'] = Configuration::get('CENTRY_SYNC_price_behavior');
    $helper->fields_value['VARIANT_SIMPLE'] = Configuration::get('CENTRY_SYNC_VARIANT_SIMPLE');
    $helper->fields_value['display_show_header'] = true;
    foreach (OrderState::getOrderStates($defaultLang) as $state) {
      $helper->fields_value[$this->l($state["id_order_state"])] = OrderStatusCentry::getIdCentry($state["id_order_state"])[0]["id_centry"];
    }

    return $helper->generateForm($fieldsForm);
  }

}
