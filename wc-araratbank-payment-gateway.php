<?php
/*
Plugin Name: Payment gateway for AraratBank
Plugin URI: #
Description: Pay with  AraratBank payment system. Please note that the payment will be made in Armenian Dram.
Version: 1.0.1
Author: HK Digital Agency LLC
Author URI: https://hkdigital.am
License: GPLv2 or later
*/

add_filter('woocommerce_payment_gateways', 'hkd_add_araratbank_gateway_class');
function hkd_add_araratbank_gateway_class($gateways)
{
    $gateways[] = 'WC_HKD_Araratbank_Arca_Gateway';
    return $gateways;
}

if (isset($_POST['woocommerce_hkd_araratbank_language_payment_araratbank'])) {
    update_option('language_payment_araratbank', $_POST['woocommerce_hkd_araratbank_language_payment_araratbank']);
}

$my_plugin_domain = 'wc-araratbank-payment-gateway';
$override_locale = !empty(get_option('language_payment_araratbank')) ? get_option('language_payment_araratbank') : 'hy';
add_filter('plugin_locale',
    function ($locale, $domain) use ($my_plugin_domain, $override_locale) {
        if ($domain == $my_plugin_domain) {
            $locale = $override_locale;
        }
        return $locale;
    }, 10, 2);

function myCronSchedulesArarat($schedules)
{
    if (!isset($schedules["30min"])) {
        $schedules["30min"] = array(
            'interval' => 30 * 60,
            'display' => __('Once every 30 minutes'));
    }
    return $schedules;
}

add_filter('cron_schedules', 'myCronSchedulesArarat');


function addCardsEndPointAraratBank()
{
    if (!wp_next_scheduled('cronCheckOrderArarat')) {
        wp_schedule_event(time(), '30min', 'cronCheckOrderArarat');
    }
    add_rewrite_endpoint('cards', EP_PERMALINK | EP_PAGES, 'cards');
    update_option("rewrite_rules", FALSE);
}

add_action('init', 'addCardsEndPointAraratBank');

function plugin_activate_hkd_araratbank()
{
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_version = $plugin_data['Version'];
    if (get_option('hkd_araratbank_version_option') !== $plugin_version) {
        try {
            if (isset($_SERVER['SERVER_NAME']) || isset($_SERVER['REQUEST_URI'])) {
                $url = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['REQUEST_URI'];
                $ip = $_SERVER['REMOTE_ADDR'];
                $token = md5('hkd_init_banks_gateway_class');
                $user = wp_get_current_user();
                $email = (string)$user->user_email;
                $data = ['plugin_name' => 'ARARAT', 'version' => $plugin_version, 'email' => $email, 'url' => $url, 'ip' => $ip, 'token' => $token, 'status' => 'inactive'];
                update_option('hkd_araratbank_version_option', $plugin_version);
                wp_remote_post('https://plugin.hkdigital.am/wp-json/hkd-payment/v1/banks-checkout/', ['sslverify' => false,'body' => $data]);
            }
        } catch (Exception $e) {
        }
    }
}

add_action('admin_init', 'plugin_activate_hkd_araratbank');

add_action('woocommerce_thankyou', 'woocomerceShowErrorMessageAraratBank', 4);
function woocomerceShowErrorMessageAraratBank($order_id)
{
    $order = wc_get_order($order_id);
    if ($order->has_status('failed')) {
        $orderFailedMessage = get_post_meta($order_id, 'FailedMessageArarat', true);
        if($orderFailedMessage) {
            echo '<div class="hkd-alert hkd-alert-danger" style="color: #a94442;background-color: #f2dede;border-color: #ebccd1;padding: 15px;margin-bottom: 20px;border: 1px solid transparent;border-radius: 4px;">
                    <strong>Error!</strong>  ' . $orderFailedMessage . '
             </div>';
        }
    }
}



add_action('plugins_loaded', 'hkd_init_araratbank_gateway_class');
function hkd_init_araratbank_gateway_class()
{

    load_plugin_textdomain('wc-araratbank-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    if (class_exists('WC_Payment_Gateway')) {
        class WC_HKD_Araratbank_Arca_Gateway extends WC_Payment_Gateway
        {
            private $api_url;
            private $currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978'];
            private $currency_code = '051';
            private $ownerSiteUrl = 'https://plugin.hkdigital.am/';

            /**
             * WC_HKD_Araratbank_Arca_Gateway constructor.
             */
            public function __construct()
            {
                global $woocommerce;

                /* Add support Refund orders */
                $this->supports = [
                    'products',
                    'refunds',
                    'subscriptions',
                    'subscription_cancellation',
                    'subscription_suspension',
                    'subscription_reactivation',
                    'subscription_amount_changes',
                    'subscription_date_changes',
                    'subscription_payment_method_change',
                    'subscription_payment_method_change_customer',
                    'subscription_payment_method_change_admin',
                    'multiple_subscriptions',
                    'gateway_scheduled_payments'
                ];

                $this->id = 'hkd_araratbank';
                $this->icon = plugin_dir_url(__FILE__) . 'assets/images/logo_araratbank.png';
                $this->has_fields = false;
                $this->method_title = 'Payment Gateway for AraratBank';
                $this->method_description = 'Pay with  AraratBank payment system. Please note that the payment will be made in Armenian Dram.';

                if (isset($_POST['hkd_araratbank_checkout_id']) && $_POST['hkd_araratbank_checkout_id'] != '') {
                    update_option('hkd_araratbank_checkout_id', sanitize_text_field($_POST['hkd_araratbank_checkout_id']));
                    $this->update_option('title', __('Pay via credit card', 'wc-araratbank-payment-gateway'));
                    $this->update_option('description', __('Purchase by credit card. Please, note that purchase is going to be made by Armenian drams. ', 'wc-araratbank-payment-gateway'));
                    $this->update_option('save_card_button_text', __('Add a credit card', 'wc-araratbank-payment-gateway'));
                    $this->update_option('save_card_header', __('Purchase safely by using your saved credit card', 'wc-araratbank-payment-gateway'));
                    $this->update_option('save_card_use_new_card', __('Use a new credit card', 'wc-araratbank-payment-gateway'));
                }

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->language_payment_araratbank = !empty($this->get_option('language_payment_araratbank')) ? $this->get_option('language_payment_araratbank') : 'hy';
                $this->enabled = $this->get_option('enabled');
                $this->hkd_arca_checkout_id = get_option('hkd_araratbank_checkout_id');
                $this->language = $this->get_option('language');
                $this->secondTypePayment = 'yes' === $this->get_option('secondTypePayment');
                $this->empty_card = 'yes' === $this->get_option('empty_card');
                $this->testmode = 'yes' === $this->get_option('testmode');
                $this->user_name = $this->testmode ? $this->get_option('test_user_name') : $this->get_option('live_user_name');
                $this->password = $this->testmode ? $this->get_option('test_password') : $this->get_option('live_password');
                $this->binding_user_name = $this->get_option('binding_user_name');
                $this->binding_password = $this->get_option('binding_password');
                $this->debug = 'yes' === $this->get_option('debug');
                $this->save_card = 'yes' === $this->get_option('save_card');
                $this->save_card_button_text = !empty($this->get_option('save_card_button_text')) ? $this->get_option('save_card_button_text') : __('Add a credit card', 'wc-araratbank-payment-gateway');
                $this->save_card_header = !empty($this->get_option('save_card_header')) ? $this->get_option('save_card_header') : __('Purchase safely by using your saved credit card', 'wc-araratbank-payment-gateway');
                $this->save_card_use_new_card = !empty($this->get_option('save_card_use_new_card')) ? $this->get_option('save_card_use_new_card') : __('Use a new credit card', 'wc-araratbank-payment-gateway');

                $this->multi_currency = 'yes' === $this->get_option('multi_currency');
                $this->api_url = !$this->testmode ? 'https://ipay.arca.am/payment/rest/' : 'https://ipaytest.arca.am:8445/payment/rest/';
                if ($this->debug) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) $this->log = $woocommerce->logger(); else $this->log = new WC_Logger();
                }
                if ($this->multi_currency) {
                    $this->currencies = ['AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978'];
                    $wooCurrency = get_woocommerce_currency();
                    $this->currency_code = $this->currencies[$wooCurrency];
                }

                // process the Change Payment "transaction"
                add_action('woocommerce_scheduled_subscription_payment', array($this, 'process_subscription_payment'), 10, 3);

                /**
                 * Success callback url for AraratBank payment api
                 */
                add_action('woocommerce_api_delete_binding_araratbank', array($this, 'delete_binding_araratbank'));

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                /**
                 * Success callback url for araratbank payment api
                 */
                add_action('woocommerce_api_araratbank_successful', array($this, 'webhook_araratbank_successful'));

                /**
                 * Failed callback url for araratbank payment api
                 */
                add_action('woocommerce_api_araratbank_failed', array($this, 'webhook_araratbank_failed'));
                /**
                 * styles and fonts for araratbank payment plugin
                 */
                add_action('admin_print_styles', array($this, 'enqueue_stylesheets'));

                /*
              * Add Credit Card Menu in My Account
              */
                if (is_user_logged_in() && $this->save_card && $this->binding_user_name != '' && $this->binding_password != '') {
                    add_filter('query_vars', array($this, 'queryVarsCards'), 0);
                    add_filter('woocommerce_account_menu_items', array($this, 'addCardLinkMenu'));
                    add_action('woocommerce_account_cards_endpoint', array($this, 'CardsPageContent'));
                }
                
                $this->checkActivation();
                
                if ($this->secondTypePayment) {
                    add_filter('woocommerce_admin_order_actions', array($this, 'add_custom_order_status_actions_button'), 100, 2);
                    add_action('admin_head', array($this, 'add_custom_order_status_actions_button_css'));
                    add_action('woocommerce_order_status_changed', array($this, 'statusChangeHook'), 10, 3);
                    add_action('woocommerce_order_edit_status', array($this, 'statusChangeHookSubscription'), 10, 2);
                }

                $this->bankErrorCodesByDiffLanguage = [
                    'hy' => [
                        '-20010' => 'Գործարքը մերժվել է, քանի որ վճարումը գերազանցել է թողարկող բանկի կողմից սահմանված սահմանաչափը',
                        '-9000' => 'Գործարքի սկզբի կարգավիճակ:',
                        '-2019' => 'Թողարկողի PARes պարունակում է iReq, ինչի արդյունքում վճարումը մերժվել է:',
                        '-2018' => 'Directory server Visa կամ MasterCard հասանելի չեն, կամ քարտի ներգավվածության հարցման(VeReq) պատասխանը չի ստացվել կապի խափանման պատճառով: Նշված սխալը հանդիսանում է վճարային ուղեմուտի և միջազգային վճարային համակարգի սերվերների փոխգործունեության արդյունքը՝ վերջիններիս տեխնիկական խափանումների պատճառով:',
                        '-2016' => 'Նշանակում է որ թողարկող բանկը (տվյալ պահին) պատրաստ չէ իրականացնել գործարքի 3ds (օրինակ՝ չի աշխատում բանկի ACS-ն) և մենք չենք կարող ստուգել արդյոք քարտը ներգրավված է 3d secure-ում, թե ոչ:',
                        '-2015' => 'DS-ի VERes-ը պարունակում է iReq, որի հետևանքով վճարումը մերժվել է:',
                        '-2013' => 'Վճարման փորձերը սպառվել են:',
                        '-2012' => 'Նշված գործարքը չի սպասարկվում համակարգի կողմից:',
                        '-2011' => 'Քարտը դիտարկվել է որպես 3d secure քարտում նեգրավված, բայց թողարկող բանկը (տվյալ պահին) պատրաստ չէ իրականացնել գործարքի 3ds:',
                        '2007' => 'Սպառվել է վճարման գրանցման պահից սկսած քարտի տվյալների մուտքագրման համար սահմանված ժամկետը (նշված պահին թայմաութը տեղի կունենա 20 րոպեից):',
                        '2006' => 'Թողարկողը մերժել է նույնականացումը:',
                        '-2005' => 'Մենք չենք կարողացել ստուգել թողարկողի ստորագրությունը, այսինքն PARes-ն ընթեռնելի էր, բայց սխալ էր ստորագրված:',
                        '2004' => 'Արգելվում է SSL –ի միջոցով առանց SVС մուտքագրման վճարում իրականացնել:',
                        '2002' => 'Գործարքը մերժվել է, քանի որ վճարումը գերազանցել է սահմանված սահմանաչափը:  Ծանոթագրություն. հաշվի են առնվում կամ էքվայեր բանկի կողմից առևտրային կետի օրական շրջանառության համար սահմանված սահմանաչափերը կամ առևտրային կետի՝ մեկ քարտով շրջանառության սահմանաչափը կամ էլ առևտրային կետի՝ մեկ գործառնության գծով սահմանաչափը:',
                        '2001' => 'Գործարքը մերժվել է, քանի որ հաճախորդի IP հասցեն գրանցված է սև ցուցակում:',
                        '2000' => 'Գործարքը մերժվել է, քանի որ քարտը գրանցված է սև ցուցակում: ',
                        '-100' => 'Վճարման փորձեր չեն եղել:',
                        '-1' => 'Սպառվել է պրոցեսինգային կետրոնի պատասխանին սպասելու ժամանակը:',
                        '1' => 'Նշված համարով պատվերն արդեն գրանցված է համակարգում:',
                        '5' => 'Հարցման պարամետրի նշանակության սխալ:',
                        '100' => 'Թողարկող բանկն արգելել է քարտով առցանց գործարքների իրականացումը:',
                        '101' => 'Քարտի գործողության ժամկետը սպառվել է:',
                        '103' => 'Թողարկող բանկի հետ կապը բացակայում է, առևտրային կետը պետք է կապ հաստատի թողարկող բանկի հետ:',
                        '104' => 'Սահմանափակումների ենթարկված հաշվով գործարք կատարելու փորձ:',
                        '107' => 'Անհրաժեշտ է դիմել թողարկող բանկին:',
                        '109' => 'Առևտրային կետի/տերմինալի նույնականացուցիչը սխալ է նշված  (ավարտի կամ տարբեր նույնականացուցիչներով նախնական հավաստագրման համար):',
                        '110' => 'Գործարքի գումարը սխալ է նշված:',
                        '111' => 'Քարտի համարը սխալ է:',
                        '116' => 'Գործարքի գումարը գերազանցում է ընտրված հաշվին առկա միջոցների հասանելի մնացորդը:',
                        '120' => 'Գործառնության մերժում. գործարքն արգելված է թողարկողի կողմից: Վճարային ցանցի պատասխանի կոդ՝ 57: Մերժման պատճառներն անհրաժեշտ է ճշտել թողարկողից:',
                        '121' => 'Ձեռնարկվել է թողարկող բանկի կողմից սահմանված օրական սահմանաչափը գերազանցող գումարով գործարքի իրականացման փորձ:',
                        '123' => 'Գերազանցվել է գործարքների թվի սահմանաչափը: Հաճախորդը կատարել է սահմանաչափի շրջանակներում թույլատրելի առավելագույն թվով գործարքները և փորձում է կատարել ևս մեկը:',
                        '125' => 'Քարտի համարը սխալ է: Սառեցված գումարը գերազանցող գումարի վերադարձման փորձ, զրոյական գումարի վերադարձման փորձ:',
                        '208' => 'Քարտը համարվում է կորած: ',
                        '209' => 'Գերազանցվել են քարտի համար սահմանված սահմանափակումները:',
                        '902' => 'Քարտապանը փորձում է կատարել իր համար արգելված գործարք:',
                        '903' => 'Ձեռնարկվել է թողարկող բանկի կողմից սահմանված սահմանաչափը գերազանցող գործարքի փորձ:',
                        '904' => 'Թողարկող բանկի տեսանկյունից հաղորդագրության սխալ ձևաչափ:',
                        '907' => 'Տվյալ քարտը թողարկած բանկի հետ կապ հաստատված չէ: Քարտի տվյալ համարի համար stand-in ռեժիմով հավաստագրում չի թույլատրվում (այսինքն  թողարկողը չի կարող կապ հաստատել վճարային ցանցի հետ, այդ պատճառով գործարքը հնարավոր է իրականացնել միայն offline ռեժիմով՝ այնուհետև փոխանցել Back Office-ին, այլապես գործարքը մերժվում է):',
                        '909' => 'Համակարգի գործունեության ընդհանուր բնույթի սխալ, որը հայտնաբերվում է վճարային ցանցի կամ թողարկող բանկի կողմից:',
                        '910' => 'Թողարկող բանկն անհասանելի է:',
                        '913' => 'Ցանցի տեսանկյունից սխալ ձևաչափ:',
                        '914' => 'Գործարքը չի գտնվել (երբ ուղարկվում է ավարտ, reversal կամ refund):',
                        '999' => 'Բացակայում է գործարքի հավաստագրման սկիզբը: Մերժվել է զեղծարարության կամ 3dsec. սխալի պատճառով:',
                        '1001' => 'Տրվում է գործարքի գրանցման պահին, այսինքն այն պահին, երբ քարտի տվյալները դեռևս չեն ներմուծվել:',
                        '2003' => 'SSL (Не 3d-Secure/SecureCode) գործարքներն արգելված են տվյալ առևտրային կետի համար:',
                        '2005' => 'Վճարումը չի համապատասխանում 3ds ստուգման կանոնների պահանջներին:',
                        '8204' => 'Նման պատվեր արդեն գրանցվել է (ստուգում ըստ ordernumber-ի):',
                        '9001' => 'RBS մերժման ներքին կոդ',
                        '1015' => 'Ներմուծվել են քարտի սխալ պարամետրեր:',
                        '1017' => '3-D Secure – կապի խափանում',
                        '1018' => 'Թայմաուտ պրոցեսինգի ընթացքում: Չի հաջողվել ուղարկել:',
                        '1019' => 'Թայմաուտ պրոցեսինգի ընթացքում: Հաջողվել է ուղարկել, բայց բանկից պատասխան չի ստացվել:',
                        '1014' => 'RBS մերժման կոդ',
                        '1041' => 'Դրամական միջոցների վերադարձման սխալ:',
                    ],
                    'ru' => [
                        '-20010' => 'Транзакция отклонена по причине того, что размер платежа превысил установленные лимиты Банком-эмитентом',
                        '-9000' => 'Состояние начала транзакции',
                        '-2019' => 'PARes от эмитента содержит iReq, вследствие чего платеж был отклонен',
                        '-2018' => 'Directory server Visa или MasterCard либо недоступен, либо в ответ на запрос вовлеченности карты (VeReq) пришла ошибка связи. Это ошибка взаимодействия платежного шлюза и серверов МПСпо причине технических неполадок на стороне последних',
                        '-2016' => 'Это означает, что банк- эммитент не готов (в данный момент времени) провести 3ds транзакцию (например, не работает ACS банка). Мы не можем определить вовлечена ли карта в 3d secure',
                        '-2015' => 'VERes от DS содержит iReq, вследствие чего платеж был отклонен',
                        '-2013' => 'Исчерпаны попытки оплаты',
                        '-2012' => 'Данная операция не поддерживается',
                        '-2011' => 'Карта определена как вовлеченная в карта 3d secure, но банк- эммитент не готов (в данный момент времени) провести 3dsтранзакцию',
                        '2007' => 'Истек срок, отведенный на ввод данных карты с момента регистрации платежа (в данный момент таймаут наступит через 20 минут)',
                        '2006' => 'Означает, что эмитент отклонил аутентификацию',
                        '-2005' => 'Означает, что мы не смогли проверить подпись эмитента, то есть PARes был читаемый, но подписан неверно.',
                        '2004' => 'Оплата через SSL без ввода SVС запрещена',
                        '2002' => 'Транзакция отклонена по причине того, что размер платежа превысил установленные лимиты. Примечание: имеется в виду либо лимиты Банка-эквайера на дневной оборот Магазина, либо лимиты Магазина на оборот по одной карте, либо лимит Магазина по одной операции)', '2001' => 'Транзакция отклонена по причине того, что IP-адрес Клиента внесен в черный список.',
                        '2000' => 'Транзакция отклонена по причине того, что карта внесена в черный список',
                        '-100' => 'Не было попыток оплаты.',
                        '-1' => 'Истекло время ожидания ответа от процессинговой системы.',
                        '1' => 'Для успешного завершения транзакции,требуется подтверждение личности. В случае интернет-транзакции (соот-но и в нашем) невозможно, поэтому считается как declined.',
                        '5' => 'Отказ сети проводить транзакцию.',
                        '100' => 'Банк эмитент запретил интернеттранзакции по карте.',
                        '101' => 'Истек срок действия карты.',
                        '103' => 'Нет связи с Банком-Эмитентом.Торговой точке необходимо связаться с банком-эмитентом.',
                        '104' => 'Попытка выполнения операции по счету, на использование которого наложены ограничения.',
                        '107' => 'Следует обратиться к Банку-Эмитенту.',
                        '109' => 'Неверно указан идентификатор мерчанта/терминала (для завершения и предавторизация с разнымиидентификаторами)',
                        '110' => 'Неверно указана сумма транзакции',
                        '111' => 'Неверный номер карты',
                        '116' => 'Сумма транзакции превышает доступный остаток средств на выбранном счете.',
                        '120' => 'Отказ в проведении операции - транзакция не разрешена эмитентом. Код ответа платежной сети - 57. Причины отказа необходимо уточнять у эмитента.',
                        '121' => 'Предпринята попытка выполнить транзакцию на сумму, превышающую дневной лимит, заданный банком-эмитентом',
                        '123' => 'Превышен лимит на число транзакций: клиент выполнил максимально разрешенное число транзакций в течение лимитного цикла и пытается провести еще одну.',
                        '125' => 'Неверный номер карты. Попытка возврата на сумму, больше холда, попытка возврата нулевой суммы.',
                        '208' => 'Карта утеряна',
                        '209' => 'Превышены ограничения по карте',
                        '902' => 'Владелец карты пытается выполнить транзакцию,которая для него не разрешена.',
                        '903' => 'Предпринята попытка выполнить транзакцию на сумму, превышающую лимит, заданный банком-эмитентом',
                        '904' => 'Ошибочный формат сообщения с точки зрения банка эмитента.',
                        '907' => 'Нет связи с Банком, выпустившим Вашу карту. Для данного номера карты не разрешена авторизация в режиме stand-in (этот режим означает, что эмитент не может связаться с платежной сетью и поэтому транзакция возможна либо в оффлайне с последующей выгрузкой в бэк офис, либо она будет отклонена)',
                        '909' => 'Ошибка функционирования системы, имеющая общий характер. Фиксируется платежной сетью или банком-эмитентом.',
                        '910' => 'Банк-эмитент недоступен.',
                        '913' => 'Неправильный форматтранзакции с точки зрения сети.',
                        '914' => 'Не найдена транзакция (когда посылается завершение или reversal или refund)',
                        '999' => 'Отсутствует начало авторизации транзакции. Отклонено по фроду или ошибка 3dsec.',
                        '1001' => 'Выставляется в момент регистрации транзакции,т.е. когда еще по транзакции не было введено данных карты',
                        '200' => 'Фродовая транзакция (по мнению процессинга или платежной сети)',
                        '2003' => 'SSL (Не 3d-Secure/SecureCode)транзакции запрещены Магазину',
                        '2005' => 'Платеж не соотвествует условиям правила проверки по 3ds',
                        '8204' => 'Такой заказ уже регистрировали (проверка по ordernumber)',
                        '9001' => 'Внутренний код отказа РБС',
                        '1015' => 'Введены неправильные параметры карты',
                        '1017' => '3-D Secure - ошибка связи',
                        '1018' => 'Таймаут в процессинге. Не удалось отправить',
                        '1019' => 'Таймаут в процессинге. Удалось отправить, но не получен ответ от банка',
                        '1014' => 'Код отказа РБС',
                        '1041' => 'Ошибка возврата денежных средств',
                    ],
                    'en' => [
                        '-20010' => 'BLOCKED_BY_LIMIT',
                        '-9000' => 'Started',
                        '-2019' => 'Decline by iReq in PARes',
                        '-2018' => 'Declined. DS connection timeout',
                        '-2016' => 'Declined. VeRes status is unknown',
                        '-2015' => 'Decline by iReq in VERes',
                        '-2013' => 'Исчерпаны попытки оплаты',
                        '-2012' => 'Operation not supported',
                        '-2011' => 'Declined. PaRes status is unknown',
                        '2007' => 'Decline. Payment time limit',
                        '2006' => 'Decline. 3DSec decline',
                        '-2005' => 'Decline. 3DSec sign error',
                        '2004' => 'SSL without CVC forbidden',
                        '2002' => 'Decline. Payment over limit',
                        '2001' => 'Decline. IP blacklisted',
                        '2000' => 'Decline. PAN blacklisted',
                        '-100' => 'no_payments_yet',
                        '-1' => 'sv_unavailable',
                        '1' => 'Declined. Honor with id',
                        '5' => 'Decline. Unable to process',
                        '100' => 'Decline. Card declined',
                        '101' => 'Decline. Expired card',
                        '103' => 'Decline. Call issuer',
                        '104' => 'Decline. Card declined',
                        '107' => 'Decline. Call issuer',
                        '109' => 'Decline. Invalid merchant',
                        '110' => 'Decline. Invalid amount',
                        '111' => 'Decline. No card record на Decline. Wrong PAN',
                        '116' => 'Decline. Decline. Not enough money',
                        '120' => 'Decline. Not allowed ',
                        '121' => 'Decline. Excds wdrwl limt',
                        '123' => 'Decline. Excds wdrwl ltmt',
                        '125' => 'Decline. Card declined',
                        '208' => 'Decline. Card is lost',
                        '209' => 'Decline. Card limitations exceeded',
                        '902' => 'Decline. Invalid trans',
                        '903' => 'Decline. Re-enter trans.',
                        '904' => 'Decline. Format error',
                        '907' => 'Decline. Host not avail.',
                        '909' => 'Decline. Call issuer',
                        '910' => 'Decline. Host not avail.',
                        '913' => 'Decline. Invalid trans',
                        '914' => 'Decline. Orig trans not found',
                        '999' => 'Declined by fraud',
                        '1001' => 'Decline. Data input timeout',
                        '200' => 'Decline. Fraud',
                        '2003' => 'Decline. SSL restricted',
                        '2005' => '3DS rule failed',
                        '8204' => 'Decline. Duplicate order',
                        '9001' => 'RBS internal error',
                        '1015' => 'Decline. Input error',
                        '1017' => 'Decline. 3DSec comm error',
                        '1018' => 'Decline. Processing timeout',
                        '1019' => 'Decline. Processing timeout',
                        '1014' => 'Decline. General Error',
                        '1041' => 'Decline. Refund failed',
                    ]
                ];
                // WP cron
                add_action('cronCheckOrderArarat', array($this, 'cronCheckOrderArarat'));
            }

            public function cronCheckOrder()
            {
                global $wpdb;
                $orders = $wpdb->get_results("
                        SELECT p.*
                        FROM {$wpdb->prefix}postmeta AS pm
                        LEFT JOIN {$wpdb->prefix}posts AS p
                        ON pm.post_id = p.ID
                        WHERE p.post_type = 'shop_order'
                        AND ( p.post_status = 'wc-on-hold' OR p.post_status = 'wc-pending')
                        AND pm.meta_key = '_payment_method'
                        AND pm.meta_value = 'hkd_araratbank'
                        ORDER BY pm.meta_value ASC, pm.post_id DESC
                    ");
                foreach ($orders as $order) {
                    $order = wc_get_order($order->ID);
                    $paymentID = get_post_meta($order->ID, 'PaymentID', true);
                    if($paymentID){
                        $response = wp_remote_post($this->api_url . '/getOrderStatus.do?orderId=' .$paymentID. '&language=' . $this->language . '&password=' . $this->password . '&userName=' . $this->user_name);
                        if (!is_wp_error($response)) {
                            $body = json_decode($response['body']);
                            if ($body->OrderStatus == 6){
                                $order->update_status('failed');
                                if ($this->debug) $this->log->add($this->id, 'Order status was changed to Failed #'.$order->ID);

                            }
                        }
                    }
                }
            }


            public function checkActivation()
            {
                try {
                    $payload = ['domain' => $_SERVER['SERVER_NAME'], 'enabled' => $this->enabled];
                    wp_remote_post($this->ownerSiteUrl . '/wp-json/hkd-payment/v1/banks-change-active/', array(
                        'sslverify' => false,
                        'method' => 'POST',
                        'body' => $payload
                    ));
                } catch (Exception $e) {

                }
            }

            public function statusChangeHookSubscription($order_id, $new_status)
            {
                $order = wc_get_order($order_id);
                if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_araratbank') {
                    if ($order->get_parent_id() > 0) {
                        if ($new_status == 'active') {
                            return $this->confirmPayment($order_id, $new_status);
                        } else if ($new_status == 'cancelled') {
                            return $this->cancelPayment($order_id);
                        }
                    }
                }
            }

            public function statusChangeHook($order_id, $old_status, $new_status)
            {
                $order = wc_get_order($order_id);
                if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_araratbank') {
                    if ($new_status == 'completed' ) {
                        return $this->confirmPayment($order_id, $new_status);
                    } else if ($new_status == 'cancelled') {
                        return $this->cancelPayment($order_id);
                    }
                }
            }

            private function getPaymentGatewayByOrder($order)
            {
                return wc_get_payment_gateway_by_order($order);
            }


            public function add_custom_order_status_actions_button_css()
            {
                echo '<style>.column-wc_actions a.cancel::after { content: "\2716" !important; color: red; }</style>';
            }

            public function add_custom_order_status_actions_button($actions, $order)
            {
                if (isset($this->getPaymentGatewayByOrder($order)->id) && $this->getPaymentGatewayByOrder($order)->id == 'hkd_araratbank') {
                    if ($order->has_status(array('processing'))) {
                        $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
                        $actions['cancelled'] = array(
                            'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_mark_order_status&status=cancelled&order_id=' . $order_id), 'woocommerce-mark-order-status'),
                            'name' => __('Cancel Order', 'woocommerce'),
                            'action' => "cancel custom",
                        );
                    }
                }
                return $actions;
            }


            public function confirmPayment($order_id, $new_status)
            {
                /* $reason */
                $order = wc_get_order($order_id);
                if (!$order->has_status('processing')) {
                    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
                    $isBindingOrder = get_post_meta($order_id, 'isBindingOrder', true);
                    $requestParams = [];
                    $amount = floatval($order->get_total()) * 100;
                    array_push($requestParams, 'amount=' . (int)$amount);
                    array_push($requestParams, 'currency=' . $this->currency_code);
                    array_push($requestParams, 'orderId=' . $PaymentID);
                    if ($isBindingOrder) {
                        array_push($requestParams, 'password=' . $this->binding_password);
                        array_push($requestParams, 'userName=' . $this->binding_user_name);
                    } else {
                        array_push($requestParams, 'password=' . $this->password);
                        array_push($requestParams, 'userName=' . $this->user_name);
                    }
                    array_push($requestParams, 'language=' . $this->language);
                    $response = wp_remote_post(
                        $this->api_url . '/deposit.do?' . implode('&', $requestParams)
                    );
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);

                        if ($body->errorCode == 0) {
                            if ($new_status == 'completed') {
                                $order->update_status('completed');
                            } else {
                                $order->update_status('active');
                            }
                            return true;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order confirm paymend #' . $order_id . '  failed.');
                            if ($new_status == 'completed') {
                                $order->update_status('processing', $body->errorMessage);
                            } else {
                                $order->update_status('on-hold', $body->errorMessage);
                            }
                            die($body->errorMessage);
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order confirm paymend #' . $order_id . '  failed.');
                        if ($new_status == 'completed') {
                            $order->update_status('processing');
                        } else {
                            $order->update_status('on-hold');
                        }
                        die('Order confirm paymend #' . $order_id . '  failed.');
                    }
                }
            }

            /**
             * Process a Cancel Payment if supported.
             *
             * @param int $order_id Order ID.
             * @return bool|WP_Error
             */
            public function cancelPayment($order_id)
            {
                /* $reason */
                $order = wc_get_order($order_id);
                if (!$order->has_status('processing')) {
                    $PaymentID = get_post_meta($order_id, 'PaymentID', true);
                    $isBindingOrder = get_post_meta($order_id, 'isBindingOrder', true);
                    $requestParams = [];
                    array_push($requestParams, 'orderId=' . $PaymentID);
                    if ($isBindingOrder) {
                        array_push($requestParams, 'password=' . $this->binding_password);
                        array_push($requestParams, 'userName=' . $this->binding_user_name);
                    } else {
                        array_push($requestParams, 'password=' . $this->password);
                        array_push($requestParams, 'userName=' . $this->user_name);
                    }
                    $response = wp_remote_post(
                        $this->api_url . '/reverse.do?' . implode('&', $requestParams)
                    );
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            $order->update_status('cancelled');
                            return true;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                            $order->update_status('processing');
                            die($body->errorMessage);
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order Cancel paymend #' . $order_id . '  failed.');
                        $order->update_status('processing');
                        die('Order Cancel paymend #' . $order_id . '  failed.');
                    }
                }
            }

            /* Refund order process */
            public function process_refund($order_id, $amount = null, $reason = '')
            {
                /* $reason */
                $order = wc_get_order($order_id);
                $requestParams = [];
                array_push($requestParams, 'amount=' . (int)$amount);
                array_push($requestParams, 'currency=' . $this->currency_code);
                array_push($requestParams, 'orderNumber=' . $order_id);
                array_push($requestParams, 'password=' . $this->password);
                array_push($requestParams, 'userName=' . $this->user_name);
                array_push($requestParams, 'language=' . $this->language);
                $response = wp_remote_post(
                    $this->api_url . '/refund.do?' . implode('&', $requestParams)
                );
                if (!is_wp_error($response)) {
                    $body = json_decode($response['body']);
                    if ($body->errorCode == 0) {
                        $order->update_status('refund');
                        return true;
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order refund paymend #' . $order_id . ' canceled or failed.');
                        return false;
                    }
                } else {
                    if ($this->debug) $this->log->add($this->id, 'Order refund paymend #' . $order_id . ' canceled or failed.');
                    return false;
                }

            }

            public function queryVarsCards($vars)
            {
                $vars[] = 'cards';
                return $vars;
            }

            public function CardsPageContent()
            {
                $plugin_url = plugin_dir_url(__FILE__);
                wp_enqueue_style('hkd-front-style', $plugin_url . "assets/css/cards.css");
                wp_enqueue_script('hkd-front-js', $plugin_url . "assets/js/cards.js");
                $html = '<div id="hkdigital_binding_info_araratbank">';
                $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_araratbank');
                if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                    $html .= '<h4 class="card_payment_title card_page">' . __('Your card list', 'wc-araratbank-payment-gateway') . '</h4>
                              <h2 class="card_payment_second card_page">' . __('You can Delete Cards', 'wc-araratbank-payment-gateway') . '</h2>
                                <ul class="card_payment_list">';
                    foreach ($bindingInfo as $key => $bindingItem) {
                        $html .= '<li class="card_item">
                                        <span class="card_subTitile">
                                        ' . __($bindingItem['cardAuthInfo']['cardholderName'] . ' |  &#8226; &#8226; &#8226; &#8226; ' . $bindingItem['cardAuthInfo']['panEnd'] . ' (expires ' . $bindingItem['cardAuthInfo']['expiration'] . ')', 'wc-araratbank-payment-gateway') . '
                                         </span>
                                         <img src="' . plugin_dir_url(__FILE__) . 'assets/images/card_types/' . $bindingItem['cardAuthInfo']['type'] . '.png" class="card_logo big_img" alt="card"/>
                                         <svg  class="svg-trash-araratbank" data-id="' . $bindingItem['bindingId'] . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                            <path fill="#ed2353"
                                                  d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z"></path>
                                        </svg>
                                    </li>';
                    }
                    $html .= '</ul>
                            </div>';
                } else {
                    $html .= '<div class="check-box noselect">
                                    <span>
                                      ' . __('No Saved Cards', 'wc-araratbank-payment-gateway') . ' 
                                    </span>
                                 </div>';
                }
                echo $html;
            }

            public function addCardLinkMenu($items)
            {
                $items['cards'] = 'Credit Cards';
                return $items;
            }

            /*
             * Delete Saved Card AJAX
             */
            public function delete_binding_araratbank()
            {
                try {
                    $bindingIdForDelete = $_REQUEST['bindingId'];
                    $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_araratbank');
                    if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                        foreach ($bindingInfo as $key => $item) {
                            if ($item['bindingId'] == $bindingIdForDelete) {
                                unset($bindingInfo[$key]);
                            }
                        }
                        delete_user_meta(get_current_user_id(), 'bindingInfo_araratbank');
                        if (count($bindingInfo) > 0)
                            add_user_meta(get_current_user_id(), 'bindingInfo_araratbank', array_values($bindingInfo));
                        $payload = [
                            'userName' => $this->user_name,
                            'password' => $this->password,
                            'bindingId' => $bindingIdForDelete
                        ];
                        wp_remote_post($this->api_url . 'unBindCard.do', array(
                            'method' => 'POST',
                            'body' => http_build_query($payload),
                            'sslverify' => is_ssl(),
                            'timeout' => 60
                        ));
                        $response = ['status' => true];
                    } else {
                        $response = ['status' => false];
                    }
                } catch (Exception $e) {
                    $response = ['status' => false];
                }
                echo json_encode($response);
                exit;
            }

            public function payment_fields()
            {
                $plugin_url = plugin_dir_url(__FILE__);
                wp_enqueue_style('hkd-front-style-araratbank', $plugin_url . "assets/css/cards.css");
                wp_enqueue_script('hkd-front-js-araratbank', $plugin_url . "assets/js/cards.js");
                $description = $this->get_description();
                if ($description) {
                    echo wpautop(wptexturize($description));  // @codingStandardsIgnoreLine.
                }
                if (is_user_logged_in() && $this->save_card && $this->binding_user_name != '' && $this->binding_password != '') {
                    $html = '<div id="hkdigital_binding_info_araratbank">';
                    $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_araratbank');
                    if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                        $html .= '<h4 class="card_payment_title"> ' . $this->save_card_header . ' </h4>
                                <ul class="card_payment_list">';

                        foreach ($bindingInfo as $key => $bindingItem) {
                            $html .= '<li class="card_item">
                                        <input   id="' . $bindingItem['bindingId'] . '" name="bindingType" value="' . $bindingItem['bindingId'] . '" type="radio" class="input-radio" name="payment_card" >
                                        <label for="' . $bindingItem['bindingId'] . '">
                                        ' . __($bindingItem['cardAuthInfo']['cardholderName'] . ' |  &#8226; &#8226; &#8226; &#8226; ' . $bindingItem['cardAuthInfo']['panEnd'] . ' (expires ' . $bindingItem['cardAuthInfo']['expiration'] . ')') . ' 
                                         </label>';
                            if ($bindingItem['cardAuthInfo']['type'] != '') {
                                $html .= '<img src="' . plugin_dir_url(__FILE__) . 'assets/images/card_types/' . $bindingItem['cardAuthInfo']['type'] . '.png" class="card_logo" alt="card">';
                            }
                            $html .= '<svg  class="svg-trash-araratbank" data-id="' . $bindingItem['bindingId'] . '" style="display: none" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                                            <path fill="#ed2353"
                                                  d="M32 464a48 48 0 0 0 48 48h288a48 48 0 0 0 48-48V128H32zm272-256a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zm-96 0a16 16 0 0 1 32 0v224a16 16 0 0 1-32 0zM432 32H312l-9.4-18.7A24 24 0 0 0 281.1 0H166.8a23.72 23.72 0 0 0-21.4 13.3L136 32H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z"></path>
                                        </svg>
                                    </li>';
                        }
                        $html .= '<li class="card_item">
                                        <input id="payment_newCard" type="radio" class="input-radio" name="bindingType" value="saveCard">
                                        <label for="payment_newCard">
                                        ' . $this->save_card_use_new_card . '
                                         </label>
                                    </li>';
                        $html .= '</ul>
                            </div>';
                    } else {
                        $html .= '<div class="check-box noselect">
                                    <input type="checkbox" id="saveCard_araratbank" name="bindingType" value="saveCard"/>
                                    <label for="saveCard_araratbank"> <span class="check"><svg class="svg-check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                        <path fill="#ffffff" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path>
                                    </svg> </span>
                                       ' . $this->save_card_button_text . '
                                    </label>
                                 </div>';
                    }
                    echo $html;
                }
            }

            public function init_form_fields()
            {
                $debug = __('Log HKD ARCA Gateway events, inside <code>woocommerce/logs/araratbank.txt</code>', 'wc-araratbank-payment-gateway');
                if (!version_compare(WOOCOMMERCE_VERSION, '2.0', '<')) {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.2.0', '<'))
                        $debug = str_replace('araratbank', $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)), $debug);
                    elseif (function_exists('wc_get_log_file_path')) {
                        $debug = str_replace('woocommerce/logs/araratbank.txt', '<a href="/wp-admin/admin.php?page=wc-status&tab=logs&log_file=' . $this->id . '-' . date('Y-m-d') . '-' . sanitize_file_name(wp_hash($this->id)) . '-log" target="_blank">' . __('here', 'wc-araratbank-payment-gateway') . '</a>', $debug);
                    }
                }
                $this->form_fields = array(
                    'language_payment_araratbank' => array(
                        'title' => __('Plugin language', 'wc-araratbank-payment-gateway'),
                        'type' => 'select',
                        'options' => [
                            'hy' => 'Հայերեն',
                            'ru_RU' => 'Русский',
                            'en_US' => 'English',
                        ],
                        'description' => __('Here you can change the language of the plugin control panel.', 'wc-araratbank-payment-gateway'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'wc-araratbank-payment-gateway'),
                        'label' => __('Enable payment gateway', 'wc-araratbank-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'wc-araratbank-payment-gateway'),
                        'type' => 'text',
                        'description' => __('User (website visitor) sees this title on order registry page as a title for purchase option.', 'wc-araratbank-payment-gateway'),
                        'default' => __('Pay via credit card', 'wc-araratbank-payment-gateway'),
                        'desc_tip' => true,
                        'placeholder' => __('Type the title', 'wc-araratbank-payment-gateway')
                    ),
                    'description' => array(
                        'title' => __('Description', 'wc-araratbank-payment-gateway'),
                        'type' => 'textarea',
                        'description' => __('User (website visitor) sees this description on order registry page in bank purchase option.', 'wc-araratbank-payment-gateway'),
                        'default' => __('Purchase by  credit card. Please, note that purchase is going to be made by Armenian drams. ', 'wc-araratbank-payment-gateway'),
                        'desc_tip' => true,
                        'placeholder' => __('Type the description', 'wc-araratbank-payment-gateway')
                    ),
                    'language' => array(
                        'title' => __('Language', 'wc-araratbank-payment-gateway'),
                        'type' => 'select',
                        'options' => [
                            'hy' => 'Հայերեն',
                            'ru' => 'Русский',
                            'en' => 'English',
                        ],
                        'description' => __('Here interface language of bank purchase can be regulated', 'wc-araratbank-payment-gateway'),
                        'default' => 'hy',
                        'desc_tip' => true,
                    ),
                    'multi_currency' => array(
                        'title' => __('Multi-Currency', 'wc-araratbank-payment-gateway'),
                        'label' => __('Enable Multi-Currency', 'wc-araratbank-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('This action, if permitted by the bank, enables to purchase by multiple currencies', 'wc-araratbank-payment-gateway'),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    'debug' => array(
                        'title' => __('Debug Log', 'wc-araratbank-payment-gateway'),
                        'type' => 'checkbox',
                        'label' => __('Enable debug mode', 'wc-araratbank-payment-gateway'),
                        'default' => 'no',
                        'description' => $debug,
                    ),
                    'testmode' => array(
                        'title' => __('Test mode', 'wc-araratbank-payment-gateway'),
                        'label' => __('Enable test Mode', 'wc-araratbank-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('To test the testing version login and password provided by the bank should be typed', 'wc-araratbank-payment-gateway'),
                        'default' => 'yes',
                        'desc_tip' => true,
                    ),
                    'test_user_name' => array(
                        'title' => __('Test User Name', 'wc-araratbank-payment-gateway'),
                        'type' => 'text',
                    ),
                    'test_password' => array(
                        'title' => __('Test Password', 'wc-araratbank-payment-gateway'),
                        'type' => 'password',
                        'placeholder' => __('Enter password', 'wc-araratbank-payment-gateway')
                    ),
                    'secondTypePayment' => array(
                        'title' => __('Two-stage Payment', 'wc-araratbank-payment-gateway'),
                        'label' => __('Enable payment confirmation function', 'wc-araratbank-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('two-stage: when the payment amount is first blocked on the buyer’s account and then at the second stage is withdrawn from the account', 'wc-araratbank-payment-gateway'),
                        'default' => 'yes',
                        'desc_tip' => true,
                    ),
                    'save_card' => array(
                        'title' => __('Save Card Admin', 'wc-araratbank-payment-gateway'),
                        'type' => 'checkbox',
                        'label' => __('Enable "Save Card" function', 'wc-araratbank-payment-gateway'),
                        'default' => 'no',
                        'desc_tip' => true,
                        'description' => __('Enable Save Card', 'wc-araratbank-payment-gateway'),
                    ),
                    'save_card_button_text' => array(
                        'title' => __('New binding card text', 'wc-araratbank-payment-gateway'),
                        'placeholder' => __('Type the save card checkbox text', 'wc-araratbank-payment-gateway'),
                        'type' => 'text',
                        'default' => __('Add a credit card', 'wc-araratbank-payment-gateway'),
                        'desc_tip' => true,
                        'description' => ' ',
                        'class' => 'saveCardInfo hiddenValue',
                    ),
                    'save_card_header' => array(
                        'title' => __('Save card description text', 'wc-araratbank-payment-gateway'),
                        'placeholder' => __('Type the save card description text', 'wc-araratbank-payment-gateway'),
                        'type' => 'text',
                        'default' => __('Purchase safely by using your saved credit card', 'wc-araratbank-payment-gateway'),
                        'desc_tip' => true,
                        'description' => ' ',
                        'class' => 'saveCardInfo hiddenValue',
                    ),
                    'save_card_use_new_card' => array(
                        'title' => __('Use new card text', 'wc-araratbank-payment-gateway'),
                        'placeholder' => __('Type the use new card text', 'wc-araratbank-payment-gateway'),
                        'type' => 'text',
                        'default' => __('Use a new credit card', 'wc-araratbank-payment-gateway'),
                        'desc_tip' => true,
                        'description' => ' ',
                        'class' => 'saveCardInfo hiddenValue'
                    ),
                    'binding_user_name' => array(
                        'title' => __('Binding User Name', 'wc-araratbank-payment-gateway'),
                        'type' => 'text',
                    ),
                    'binding_password' => array(
                        'title' => __('Binding Password', 'wc-araratbank-payment-gateway'),
                        'type' => 'password',
                        'placeholder' => __('Enter password', 'wc-araratbank-payment-gateway')
                    ),
                    'live_settings' => array(
                        'title' => __('Live Settings', 'wc-araratbank-payment-gateway'),
                        'type' => 'hidden'
                    ),
                    'live_user_name' => array(
                        'title' => __('User Name', 'wc-araratbank-payment-gateway'),
                        'type' => 'text',
                        'placeholder' => __('Type the user name', 'wc-araratbank-payment-gateway')
                    ),
                    'live_password' => array(
                        'title' => __('Password', 'wc-araratbank-payment-gateway'),
                        'type' => 'password',
                        'placeholder' => __('Type the password', 'wc-araratbank-payment-gateway')
                    ),
                    'useful_functions' => array(
                        'title' => __('Useful functions', 'wc-araratbank-payment-gateway'),
                        'type' => 'hidden'
                    ),
                    'empty_card' => array(
                        'title' => __('Cart totals', 'wc-araratbank-payment-gateway'),
                        'label' => __('Activate shopping cart function', 'wc-araratbank-payment-gateway'),
                        'type' => 'checkbox',
                        'description' => __('This feature ensures that the contents of the shopping cart are available at the time of order registration if the site buyer decides to change the payment method.', 'wc-araratbank-payment-gateway'),
                        'default' => 'yes',
                        'desc_tip' => true,
                    ),
                    'links' => array(
                        'title' => __('Links', 'wc-araratbank-payment-gateway'),
                        'type' => 'hidden'
                    ),
                );
            }


            public function process_payment($order_id)
            {
                global $woocommerce;
                if (isset($_REQUEST['bindingType'])) $bindingType = $_REQUEST['bindingType'];
                $order = wc_get_order($order_id);
                $amount = floatval($order->get_total()) * 100;
                $requestParams = [];
                array_push($requestParams, 'amount=' . (int)$amount);
                array_push($requestParams, 'currency=' . $this->currency_code);
                array_push($requestParams, 'orderNumber=' . $order_id);
                array_push($requestParams, 'language=' . $this->language);
                if (isset($bindingType) && $bindingType != 'saveCard') {
                    array_push($requestParams, 'password=' . $this->binding_password);
                    array_push($requestParams, 'userName=' . $this->binding_user_name);
                } else {
                    array_push($requestParams, 'password=' . $this->password);
                    array_push($requestParams, 'userName=' . $this->user_name);
                }
                array_push($requestParams, 'description=Order N' . $order_id);
                array_push($requestParams, 'returnUrl=' . get_site_url() . '/wc-api/araratbank_successful?order=' . $order_id);
                array_push($requestParams, 'failUrl=' . get_site_url() . '/wc-api/araratbank_failed?order=' . $order_id);
                if (isset($bindingType) && $bindingType != 'saveCard') {
                    array_push($requestParams, 'clientId=' . get_current_user_id());
                    $response = ($this->secondTypePayment) ? wp_remote_post(
                        $this->api_url . '/registerPreAuth.do?' . implode('&', $requestParams)
                    ) : wp_remote_post(
                        $this->api_url . '/register.do?' . implode('&', $requestParams)
                    );
                    $body = json_decode($response['body']);
                    $payload = [
                        'userName' => $this->binding_user_name,
                        'password' => $this->binding_password,
                        'mdOrder' => $body->orderId,
                        'bindingId' => $_REQUEST['bindingType']
                    ];
                    $response = wp_remote_post($this->api_url . '/paymentOrderBinding.do', array(
                        'method' => 'POST',
                        'body' => http_build_query($payload),
                        'sslverify' => is_ssl(),
                        'timeout' => 60
                    ));
                    $body = json_decode($response['body']);
                    if ($body->errorCode == 0) {
                        $order->update_status('processing');
                        $parts = parse_url($body->redirect);
                        parse_str($parts['query'], $query);
                        update_post_meta($order_id, 'PaymentID', $query['orderId']);
                        update_post_meta($order_id, 'isBindingOrder', 1);
                        wc_reduce_stock_levels($order_id);
                        if(!$this->empty_card) {
                            $woocommerce->cart->empty_cart();
                        }
                        return array('result' => 'success', 'redirect' => $body->redirect);
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                        $order->update_status('failed', $body->errorMessage);
                        wc_add_notice(__('Please try again.', 'wc-araratbank-payment-gateway'), 'error');
                    }
                }
                update_post_meta($_REQUEST['order'], 'isBindingOrder', 0);
                if (($this->save_card && $this->binding_user_name != '' && $this->binding_password != '' && is_user_logged_in() && isset($bindingType) && $bindingType == 'saveCard') || (function_exists('wcs_get_subscriptions_for_order') && !empty(wcs_get_subscriptions_for_order($order_id, array('order_type' => 'any'))))) {
                    array_push($requestParams, 'clientId=' . get_current_user_id());
                }
                $response = ($this->secondTypePayment) ? wp_remote_post(
                    $this->api_url . '/registerPreAuth.do?' . implode('&', $requestParams)
                ) : wp_remote_post(
                    $this->api_url . '/register.do?' . implode('&', $requestParams)
                );

                if (!is_wp_error($response)) {
                    $body = json_decode($response['body']);
                    if ($body->errorCode == 0) {
                        $order->update_status('pending');
                        wc_reduce_stock_levels($order_id);
                        if(!$this->empty_card) {
                            $woocommerce->cart->empty_cart();
                        }
                        return array('result' => 'success', 'redirect' => $body->formUrl);
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                        $order->update_status('failed', $body->errorMessage);
                        wc_add_notice(__('Please try again.', 'wc-araratbank-payment-gateway'), 'error');
                    }
                } else {
                    if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                    $order->update_status('failed');
                    wc_add_notice(__('Connection error.', 'wc-araratbank-payment-gateway'), 'error');
                }
            }


            public function enqueue_stylesheets()
            {
                $plugin_url = plugin_dir_url(__FILE__);
                wp_enqueue_script('hkd-araratbank-front-admin-js', $plugin_url . "assets/js/admin.js");
                wp_localize_script('hkd-araratbank-front-admin-js', 'myScript', array(
                    'pluginsUrl' => $plugin_url,
                ));
                wp_enqueue_style('hkd-style-araratbank', $plugin_url . "assets/css/style.css");
                wp_enqueue_style('hkd-style-awesome-araratbank', $plugin_url . "assets/css/font_awesome.css");
            }

            public function process_subscription_payment($order_id)
            {
                $order = wc_get_order($order_id);
                if ($this->getPaymentGatewayByOrder($order)->id == 'hkd_araratbank') {
                    $bindingInfo = get_user_meta($order->get_user_id(), 'recurringChargeARARAT' . (int)$order->get_parent_id());

                    $amount = floatval($order->get_total()) * 100;
                    $requestParams = [];
                    array_push($requestParams, 'amount=' . (int)$amount);
                    array_push($requestParams, 'currency=' . $this->currency_code);
                    array_push($requestParams, 'orderNumber=' . rand(10000000, 99999999));
                    array_push($requestParams, 'language=' . $this->language);
                    array_push($requestParams, 'password=' . $this->binding_password);
                    array_push($requestParams, 'userName=' . $this->binding_user_name);
                    array_push($requestParams, 'description=Order N' . $order_id);
                    array_push($requestParams, 'returnUrl=' . get_site_url() . '/wc-api/araratbank_successful?order=' . $order_id);
                    array_push($requestParams, 'failUrl=' . get_site_url() . '/wc-api/araratbank_failed?order=' . $order_id);
                    array_push($requestParams, 'clientId=' . get_current_user_id());
                    $response = ($this->secondTypePayment) ? wp_remote_post(
                        $this->api_url . '/registerPreAuth.do?' . implode('&', $requestParams)
                    ) : wp_remote_post(
                        $this->api_url . '/register.do?' . implode('&', $requestParams)
                    );
                    $body = json_decode($response['body']);
                    update_post_meta($order_id, 'PaymentID', $body->orderId);
                    $payload = [
                        'userName' => $this->binding_user_name,
                        'password' => $this->binding_password,
                        'mdOrder' => $body->orderId,
                        'bindingId' => $bindingInfo[0]['bindingId']
                    ];
                    $response = wp_remote_post($this->api_url . '/paymentOrderBinding.do', array(
                        'method' => 'POST',
                        'body' => http_build_query($payload),
                        'sslverify' => is_ssl(),
                        'timeout' => 60
                    ));
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            if ($this->secondTypePayment) {
                                $order->update_status('on-hold');
                            } else {
                                $order->update_status('active');
                            }
                            $parts = parse_url($body->redirect);
                            parse_str($parts['query'], $query);
                            update_post_meta($order_id, 'isBindingOrder', 1);
                            return true;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'Order payment #' . $order_id . ' canceled or failed.');
                            $order->update_status('pending-cancel');
                            echo "<pre>";
                            print_r($body);
                            echo "error";
                            exit;
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback.');
                        $order->update_status('pending-cancel', 'WP Error binding payment');
                        echo "error";
                        exit;
                    }
                }
            }
            
            public function admin_options()
            {
                $validate = $this->validateFields();

                if ($validate['status'] !== 'success') {
                    $message = $validate['message'];
                }

                if (!empty($message)) { ?>
                    <div id="message" class="<?= ($validate['status'] === 'success') ? 'updated' : 'error' ?> fade">
                        <p><?php echo $message; ?></p>
                    </div>
                <?php } ?>
                <div class="wrap-araratbank wrap-content wrap-content-hkd"
                     style="width: 45%;display: inline-block;vertical-align: text-bottom;">
                    <h4><?= __('ONLINE PAYMENT GATEWAY', 'wc-araratbank-payment-gateway') ?></h4>
                    <h3><?= __('ARARAT BANK', 'wc-araratbank-payment-gateway') ?></h3>
                    <?php if ($validate['status'] != 'success'): ?>
                        <div style="width: 400px; padding-bottom: 60px">
                            <p style="padding-bottom: 10px"><?php echo __('Before using the plugin, please contact the bank to receive respective regulations.', 'wc-araratbank-payment-gateway'); ?></p>
                        </div>
                    <?php endif; ?>
                    <table class="form-table">
                        <?php if ($validate['status'] === 'success') {
                            $this->generate_settings_html(); ?>
                            <tr valign="top">
                                <th scope="row">AraratBank callback Url Success</th>
                                <td><?= get_site_url() ?>/wc-api/araratbank_successful</td>
                            </tr>
                        <?php } else { ?>
                            <tr valign="top">
                                <td style="display: block;width: 100%;padding-left: 0 !important;">
                                    <label style="display: block;padding-bottom: 3px"
                                           for="woocommerce_hkd_araratbank_language_payment_araratbank"><?php echo __('Plugin language', 'wc-araratbank-payment-gateway') ?></label>
                                    <fieldset>
                                        <select class="select " name="woocommerce_hkd_araratbank_language_payment_araratbank"
                                                id="woocommerce_hkd_araratbank_language_payment_araratbank" style="">
                                            <option value="hy" <?php if ($this->language_payment_araratbank == 'hy'): ?> selected <?php endif; ?> >
                                                Հայերեն
                                            </option>
                                            <option value="ru_RU" <?php if ($this->language_payment_araratbank == 'ru_RU'): ?> selected <?php endif; ?> >
                                                Русский
                                            </option>
                                            <option value="en_US" <?php if ($this->language_payment_araratbank == 'en_US'): ?> selected <?php endif; ?> >
                                                English
                                            </option>
                                        </select>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr valign="top">
                                <td style="display: block;width: 100%;padding-left: 0 !important;">
                                    <label style="display: block;padding-bottom: 3px"><?php echo __('Identification password', 'wc-araratbank-payment-gateway'); ?></label>
                                    <input type="text" placeholder="<?php echo __('Example AraratBankgayudcsu14', 'wc-araratbank-payment-gateway')?>"
                                           name="hkd_araratbank_checkout_id" id="hkd_arca_checkout_id"
                                           value="<?php echo $this->hkd_arca_checkout_id; ?>"/>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                    <?php if ($validate['status'] != 'success'): ?>
                        <div>
                            <div style="margin-top: 190px;margin-bottom: 15px;">
                                <i style="font-size: 18px" class="phone-icon-2 fa fa-info-circle"></i>
                                <span style="width: calc(400px - 25px);display: inline-block;vertical-align: middle;font-size: 14px;font-weight:600;font-style: italic;font-family: sans-serif;">
                                    <?php echo __('To see the identification terms, click', 'wc-araratbank-payment-gateway'); ?> <a
                                            class="informationLink" target="_blank"
                                            href="https://hkdigital.am"><?php echo __('here', 'wc-araratbank-payment-gateway'); ?></a>
                        </span>
                            </div>
                            <div style="font-size: 16px;font-weight: 600;margin-top: 30px;margin-bottom: 10px;">  <?php echo __('Useful links', 'wc-araratbank-payment-gateway'); ?>
                            </div>
                            <div class="araratbank_info">
                                <ul style="list-style: none;margin: 0; padding: 0;font-size: 16px;font-weight: 600;font-style: italic;">
                                    <li>
                                        <i class="phone-icon-2 fa fa-link"></i>
                                        <a target="_blank"
                                           href="https://www.araratbank.am/hy/Business/business-tools/payment-solutions">
                                            <?php echo __('See bank offer', 'wc-araratbank-payment-gateway'); ?>
                                        </a>
                                    </li>
                                    <li>
                                        <i class="phone-icon-2 fa fa-link"></i>
                                        <a target="_blank"
                                           href="https://wordpress.org/plugins/wc-araratbank-payment-gateway/">
                                            <?php echo __('See plugin possibilities', 'wc-araratbank-payment-gateway'); ?>
                                        </a>
                                    </li>
                                    <li>
                                        <i class="phone-icon-2 fa fa-link"></i>
                                        <a target="_blank" href="https://hkdigital.am">
                                            <?php echo __('See terms of usage', 'wc-araratbank-payment-gateway'); ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>


                </div>
                <div class="wrap-araratbank wrap-content wrap-content-hkd"
                     style="width: 29%;display: inline-block;position: absolute; padding-top: 75px;">
                    <div class="wrap-content-hkd-400px">
                        <img width="341" height="140"
                             src="<?= plugin_dir_url(__FILE__) ?>assets/images/hkserperator.png">
                    </div>
                    <div class=" wrap-content-hkd-400px">
                        <img src="<?= plugin_dir_url(__FILE__) ?>assets/images/logo_hkd.png">
                        <div class="wrap-content-hkd-info">
                            <div class="wrap-content-info">
                                <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                                </div>
                                <p><a href="tel:+37460777999">060777999</a></p>
                                <div class="phone-icon-2 icon"><i class="fa fa-phone"></i>
                                </div>
                                <p><a href="tel:+37433779779">033779779</a></p>
                                <div class="mail-icon-2 icon"><i class="fa fa-envelope"></i></div>
                                <p><a href="mailto:support@hkdigital.am">support@hkdigital.am</a></p>
                                <div class="mail-icon-2 icon"><i class="fa fa-link"></i></div>
                                <p><a target="_blank" href="https://www.hkdigital.am">hkdigital.am</a></p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php }

            /**
             * @return array|mixed|object
             */
            public function validateFields()
            {
                $go = get_option('hkdump');
                $wooCurrency = get_woocommerce_currency();

                if (!isset($this->currencies[$wooCurrency])) {
                    $this->update_option('enabled', 'no');
                    return ['message' => 'Դուք այժմ օգտագործում եք ' . $wooCurrency . ' արժույթը, այն չի սպասարկվում բանկի կողմից։
                                          Հասանելի արժույթներն են ՝  ' . implode(', ', array_keys($this->currencies)), 'status' => 'currency_error'];
                }
                if ($this->hkd_arca_checkout_id == '') {
                    if (!empty($go)) {
                        update_option('hkdump', 'no');
                    } else {
                        add_option('hkdump', 'no');
                    };
                    $this->update_option('enabled', 'no');
                    return ['message' => __('You must fill token', 'wc-araratbank-payment-gateway'), 'status' => false];
                }
                $ch = curl_init($this->ownerSiteUrl .
                    '/wp-json/hkd-payment/v1/checkout/');

                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['checkIn' => true]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                    ]
                );
                $res = curl_exec($ch);
                curl_close($ch);
                if ($res) {
                    $response = wp_remote_post($this->ownerSiteUrl .
                        '/wp-json/hkd-payment/v1/checkout/', ['sslverify' => false,'body' => ['domain' => $_SERVER['SERVER_NAME'], 'checkoutId' => $this->hkd_arca_checkout_id]]);
                    if (!is_wp_error($response)) {
                        if (!empty($go)) {
                            update_option('hkdump', 'yes');
                        } else {
                            add_option('hkdump', 'yes');
                        };
                        return json_decode($response['body'], true);
                    } else {
                        if (!empty($go)) {
                            update_option('hkdump', 'no');
                        } else {
                            add_option('hkdump', 'no');
                        };
                        $this->update_option('enabled', 'no');
                        return ['message' => __('Token not valid', 'wc-araratbank-payment-gateway'), 'status' => false];
                    }
                } else {
                    if (get_option('hkdump') == 'yes') {
                        return ['message' => '', 'status' => 'success'];
                    } else {
                        return ['message' => __('You must fill token', 'wc-araratbank-payment-gateway'), 'status' => false];
                    }
                }

            }


            public function webhook_araratbank_successful()
            {
                global $woocommerce;
                if($this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                if (isset($_REQUEST['order']) && $_REQUEST['order'] !== '') {
                    $isBindingOrder = get_post_meta($_REQUEST['order'], 'isBindingOrder', true);
                    if ($isBindingOrder) {
                        $response = wp_remote_post($this->api_url . '/getOrderStatusExtended.do?orderId=' . sanitize_text_field($_REQUEST['orderId']) . '&language=' . $this->language . '&password=' . $this->binding_password . '&userName=' . $this->binding_user_name);
                    } else {
                        $response = wp_remote_post($this->api_url . '/getOrderStatusExtended.do?orderId=' . sanitize_text_field($_REQUEST['orderId']) . '&language=' . $this->language . '&password=' . $this->password . '&userName=' . $this->user_name);
                    }
                    $body = json_decode($response['body']);
                    $user_meta_key = 'bindingInfo_araratbank';
                    if(isset($body->bindingInfo->bindingId)){
                        add_user_meta(get_current_user_id(), 'recurringChargeARARAT' . $_REQUEST['order'], ['bindingId' => $body->bindingInfo->bindingId]);
                    }
                    if ($this->save_card && $this->binding_user_name != '' && $this->binding_password != '' && is_user_logged_in() && isset($body->bindingInfo) && isset($body->cardAuthInfo)) {
                        $bindingInfo = get_user_meta(get_current_user_id(), 'bindingInfo_araratbank');
                        $findCard = false;
                        if (is_array($bindingInfo) && count($bindingInfo) > 0) {
                            foreach ($bindingInfo as $key => $bindingItem) {
                                if ($bindingItem['cardAuthInfo']['expiration'] == substr($body->cardAuthInfo->expiration, 0, 4) . '/' . substr($body->cardAuthInfo->expiration, 4) && $bindingItem['cardAuthInfo']['panEnd'] == substr($body->cardAuthInfo->pan, -4)) {
                                    $findCard = true;
                                }
                            }
                        }
                        if (!$findCard) {
                            $metaArray = array(
                                'active' => true,
                                'bindingId' => $body->bindingInfo->bindingId,
                                'cardAuthInfo' => [
                                    'expiration' => substr($body->cardAuthInfo->expiration, 0, 4) . '/' . substr($body->cardAuthInfo->expiration, 4),
                                    'cardholderName' => $body->cardAuthInfo->cardholderName,
                                    'pan' => substr($body->cardAuthInfo->pan, 0, 4) . str_repeat('*', strlen($body->cardAuthInfo->pan) - 8) . substr($body->cardAuthInfo->pan, -4),
                                    'panEnd' => substr($body->cardAuthInfo->pan, -4),
                                    'type' => $this->getCardType($body->cardAuthInfo->pan)
                                ],
                            );
                            $user_id = $body->bindingInfo->clientId;
                            add_user_meta($user_id, $user_meta_key, $metaArray);
                        }
                    }
                    update_post_meta($_REQUEST['order'], 'PaymentID', $_REQUEST['orderId']);

                    $order = wc_get_order(sanitize_text_field($_REQUEST['order']));
                    $order->update_status('processing');
                    if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_REQUEST['order']) . ' successfully added to processing');
                    echo $this->get_return_url($order);
                    wp_redirect($this->get_return_url($order));
                    exit;
                }

                if (isset($_REQUEST['orderId']) && $_REQUEST['orderId'] !== '') {

                    $response = wp_remote_post($this->api_url . '/getOrderStatus.do?orderId=' . sanitize_text_field($_REQUEST['orderId']) . '&language=' . $this->language . '&password=' . $this->password . '&userName=' . $this->user_name);

                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if ($body->errorCode == 0) {
                            $order = wc_get_order($body->OrderNumber);
                            $order->update_status('processing');
                            if ($this->debug) $this->log->add($this->id, 'Order #' . $body->OrderNumber . ' successfully added to processing.');
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_REQUEST['orderId']) . '. Error: ' . $body->errorMessage);
                        }
                    } else {
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_REQUEST['orderId']));
                    }
                }

                wc_add_notice(__('Please try again later.', 'wc-araratbank-payment-gateway'), 'error');
                wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                exit;
            }

            public function webhook_araratbank_failed()
            {
                global $woocommerce;
                if($this->empty_card) {
                    $woocommerce->cart->empty_cart();
                }
                if (isset($_GET['orderId']) && $_GET['orderId'] !== '') {
                    $order = wc_get_order(sanitize_text_field($_GET['order']));
                    if ($this->debug) $this->log->add($this->id, 'Order #' . sanitize_text_field($_GET['order']) . ' failed.');
                    $response = wp_remote_post($this->api_url . '/getOrderStatus.do?orderId=' . sanitize_text_field($_GET['orderId']) . '&language=' . $this->language . '&password=' . $this->password . '&userName=' . $this->user_name);
                    if (!is_wp_error($response)) {
                        $body = json_decode($response['body']);
                        if (isset($this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse])) {
                            $order = new WC_Order(sanitize_text_field($_GET['order']));
                            $errMessage = $this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse];
                            $order->add_order_note($errMessage, true);
                            $order->update_status('failed');
                            if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_GET['orderId']) . '. Error: ' . $this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse]);
                            update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageArarat', $this->bankErrorCodesByDiffLanguage[$this->language][$body->SvfeResponse]);
                        } else {
                            $order->update_status('failed');
                            update_post_meta(sanitize_text_field($_GET['order']), 'FailedMessageArarat', __('Please try again later.', 'wc-araratbank-payment-gateway'));
                        }
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with  Arca callback: #' . sanitize_text_field($_GET['orderId']) . '. Error: ' . $body->errorMessage);
                        wp_redirect($this->get_return_url($order));
                        exit;
                    } else {
                        $order->update_status('failed');
                        wc_add_notice(__('Please try again later.', 'wc-araratbank-payment-gateway'), 'error');
                        if ($this->debug) $this->log->add($this->id, 'something went wrong with Arca callback: #' . sanitize_text_field($_GET['orderId']));
                    }
                }
                wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
                exit;
            }


            public function getCardType($cardNumber)
            {
                $explodedCardNumber = explode('*', $cardNumber);
                $explodedCardNumber[1] = mt_rand(100000, 999999);
                $cardNumber = implode('', $explodedCardNumber);
                $type = '';
                $regex = [
                    'electron' => '/^(4026|417500|4405|4508|4844|4913|4917)\d+$/',
                    'maestro' => '/^(5018|5020|5038|5612|5893|6304|6759|6761|6762|6763|0604|6390)\d+$/',
                    'dankort' => '/^(5019)\d+$/',
                    'interpayment' => '/^(636)\d+$/',
                    'unionpay' => '/^(62|88)\d+$/',
                    'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
                    'master_card' => '/^5[1-5][0-9]{14}$/',
                    'amex' => '/^3[47][0-9]{13}$/',
                    'diners' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
                    'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
                    'jcb' => '/^(?:2131|1800|35\d{3})\d{11}$/'
                ];
                foreach ($regex as $key => $item) {
                    if (preg_match($item, $cardNumber)) {
                        $type = $key;
                        break;
                    }
                }
                return $type;
            }
        }
    }

    add_action('plugin_action_links_' . plugin_basename(__FILE__), 'hkd_araratbank_gateway_setting_link');
    function hkd_araratbank_gateway_setting_link($links)
    {
        $links = array_merge(array(
            '<a href="' . esc_url(admin_url('/admin.php')) . '?page=wc-settings&tab=checkout&section=hkd_araratbank">' . __('Settings', 'wc-araratbank-payment-gateway') . '</a>'
        ), $links);
        return $links;
    }
}
