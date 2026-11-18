<?php
declare(strict_types=1);
namespace Stripe {
    class Subscription {
        public static array $updates=[];
        public static function retrieve($id) { return (object)['trial_end'=>time()+7*86400]; }
        public static function update($id,$args) { self::$updates[]=[$id,$args]; return (object)$args; }
    }
}
namespace Stripe\Checkout {
    class Session {
        public static array $calls=[];
        public static function create($params,$options) { self::$calls[]=[$params,$options];return (object)['id'=>'cs_fake_'.count(self::$calls),'url'=>'https://checkout.stripe.test/session']; }
    }
}
namespace {
require dirname(__DIR__).'/lib/hosting_catalog.php';
require dirname(__DIR__).'/lib/hosting_trials.php';
const RATE_LIMIT_CHECKOUT=10;
const DNCT_TERMS_VERSION='test';
const PROVISION_GRACE_HOURS=24;
class Reply extends \RuntimeException {public function __construct(public int $status,public array $data){parent::__construct();}}
class Duplicate extends \PDOException {public function __construct(){ $this->code='23000'; }}
class MemoryDB extends \PDO {
    public array $orders=[], $trials=[]; public int $last=0;
    public function __construct(){}
    public function prepare(string $query,array $options=[]): \PDOStatement|false {return new MemoryStatement($this,$query);}
    public function lastInsertId(?string $name=null): string|false{return (string)$this->last;}
}
class MemoryStatement extends \PDOStatement {
    private mixed $value=false; private int $affected=0;
    public function __construct(private MemoryDB $db,private string $sql){}
    public function execute(?array $params=null): bool {
        $p=$params??[];$s=preg_replace('/\s+/',' ',trim($this->sql));$this->value=false;$this->affected=0;
        if(str_starts_with($s,'SELECT GET_LOCK')){$this->value=1;return true;}
        if(str_starts_with($s,'SELECT RELEASE_LOCK')){return true;}
        if(str_starts_with($s,'INSERT INTO hosting_orders')){
            $id=++$this->db->last;$this->db->orders[$id]=['id'=>$id,'user_email'=>$p[0],'user_name'=>$p[1],'package'=>$p[2],'period_days'=>$p[3],'billing_mode'=>$p[4],'amount_gross'=>$p[5],'extends_order_id'=>$p[6],'status'=>'pending','stripe_session_id'=>null,'stripe_subscription_id'=>null,'stripe_customer_id'=>null,'expires_at'=>null,'hosting_user'=>null,'suspended_at'=>null,'immediate_service_requested_at'=>!empty($p[8])?'now':null,'service_start_at'=>'now'];return true;
        }
        if(str_starts_with($s,'DELETE FROM hosting_trial_claims')){
            foreach($this->db->trials as $id=>$t){if(($t['account_key']===$p[0]||$t['user_email']===$p[1])&&$t['started_at']===null&&$t['checkout_expires_at']<time())unset($this->db->trials[$id]);}return true;
        }
        if(str_starts_with($s,'INSERT INTO hosting_trial_claims')){
            foreach($this->db->trials as $t)if($t['account_key']===$p[0]||$t['user_email']===$p[1]||$t['order_id']===$p[2])throw new Duplicate();
            $this->db->trials[$p[2]]=['account_key'=>$p[0],'user_email'=>$p[1],'order_id'=>$p[2],'started_at'=>null,'ends_at'=>null,'checkout_expires_at'=>time()+1860];return true;
        }
        if(str_starts_with($s,'SELECT * FROM hosting_trial_claims')){$this->value=$this->db->trials[$p[0]]??false;return true;}
        if(str_starts_with($s,'SELECT * FROM hosting_orders')){
            if(str_contains($s,'WHERE user_email')){foreach(array_reverse($this->db->orders,true)as$o){if($o['user_email']===$p[0]&&$o['extends_order_id']===null&&in_array($o['status'],['paid','provisioned'])){$this->value=$o;break;}}}
            else {preg_match('/WHERE (\w+) =/',$s,$m);foreach($this->db->orders as$o){if((string)($o[$m[1]]??'')===(string)$p[0]){$this->value=$o;break;}}}return true;
        }
        if(str_starts_with($s,'UPDATE hosting_trial_claims SET started_at')){$this->db->trials[$p[1]]['started_at']='now';$this->db->trials[$p[1]]['ends_at']=$p[0];return true;}
        if(str_starts_with($s,'UPDATE hosting_orders SET stripe_session_id')){$this->db->orders[$p[1]]['stripe_session_id']=$p[0];return true;}
        if(str_starts_with($s,'UPDATE hosting_orders SET status = "cancelled"')){$this->db->orders[$p[0]]['status']='cancelled';return true;}
        if(str_starts_with($s,'UPDATE hosting_orders SET status = "paid"')){
            $id=$p[4];$o=&$this->db->orders[$id];$o['status']='paid';$o['stripe_customer_id']=$p[0];$o['stripe_subscription_id']=$p[1];$o['expires_at']=$p[2]??gmdate('Y-m-d H:i:s',time()+30*86400);return true;
        }
        if(str_starts_with($s,'UPDATE hosting_orders SET status = "provisioned", hosting_user')){$this->db->orders[$p[1]]['status']='provisioned';$this->db->orders[$p[1]]['hosting_user']=$p[0];return true;}
        if(str_starts_with($s,'UPDATE hosting_orders SET expires_at = GREATEST')){$this->db->orders[$p[2]]['expires_at']=max($this->db->orders[$p[2]]['expires_at'],$p[0]);return true;}
        if(str_starts_with($s,'UPDATE hosting_orders SET billing_mode = "payment"')){$this->db->orders[$p[0]]['billing_mode']='payment';return true;}
        throw new \RuntimeException('Nieobsłużone SQL w teście: '.$s);
    }
    public function fetch(int $mode=\PDO::FETCH_DEFAULT,int $cursorOrientation=\PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->value;}
    public function fetchColumn(int $column=0):mixed{return $this->value;}
    public function rowCount():int{return $this->affected;}
}
function source(string $path):string { $s=file_get_contents($path);$s=preg_replace('/^<\?php\s*/','',$s);$s=preg_replace('/declare\(strict_types=1\);/','',$s);return preg_replace('/^require(?:_once)? .*?;\s*$/m','',$s); }
eval(source(dirname(__DIR__).'/lib/hosting_orders.php'));
$db=new MemoryDB();$request=[];$provisions=0;$mailCount=0;
function dnct_db(){return $GLOBALS['db'];}
function require_method($m){} function csrf_guard(){} function rate_limit_guard(...$a){}
function stripe_sales_enabled(){return true;}function hosting_sales_enabled(){return true;}
function request_string($key,$default=''){return (string)($GLOBALS['request'][$key]??$default);}
function request_bool($key){return !empty($GLOBALS['request'][$key]);}
function json_error($message,$status=400,...$extra){throw new Reply($status,['ok'=>false,'message'=>$message]);}
function json_ok($data){throw new Reply(200,['ok'=>true]+$data);}
function stripe_init(){} function stripe_interval_for_days($d){return ['interval'=>'day','interval_count'=>$d];}
function hosting_site_url(){return 'https://web.example.invalid';}function hosting_public_url(){return 'https://panel.example.invalid';}
function pricing_format($n){return number_format($n/100,2,',','');}
function hosting_call($action,$args){$GLOBALS['provisions']++;return ['user'=>'fake-user'];}
function notify_user(...$args){}function notify_admins(...$args){}function ntfy_send(...$args){}
function send_mail(...$args){$GLOBALS['mailCount']++;return ['ok'=>true];}
function czas_fmt($value,...$args){return (string)$value;}
function order_contract_confirmation_text($o){return '';}
function check($condition,$message){if(!$condition)throw new \RuntimeException($message);echo "PASS $message\n";}
function checkout(array $input):Reply {
    $GLOBALS['request']=$input;
    try{eval(source(dirname(__DIR__,2).'/api/checkout/hosting.php'));}catch(Reply $r){return $r;}
    throw new \RuntimeException('Brak odpowiedzi');
}
function fresh(){ $GLOBALS['db']=new MemoryDB();$_SESSION=['authentik_email'=>'qa@example.invalid','authentik_username'=>'qa','authentik_sub'=>'stable-account']; }
function input($extra=[]){return $extra+['package'=>'dnct-web-pro','billing_mode'=>'subscription','terms_accepted'=>true,'immediate_service_start'=>true];}
fresh();$r=checkout(input(['billing_mode'=>'payment','unit_amount'=>1]));$last=\Stripe\Checkout\Session::$calls[array_key_last(\Stripe\Checkout\Session::$calls)];
check($r->status===200&&$last[0]['line_items'][0]['price_data']['unit_amount']===2199,'Kwota jednorazowa Pro z serwera, próba podmiany kwoty ignorowana');
check(hosting_price('dnct-web-start','payment')===1099&&hosting_price('dnct-web-biznes','payment')===4399,'Dopłata 10% zaokrąglona do grosza');
check(hosting_price('dnct-start','payment')===900,'Cena istniejącego planu pozostaje bez zmian');
check(hosting_price('dnct-web-pro','subscription','year')===1999*11&&hosting_price('dnct-web-pro','payment','year')===hosting_price('dnct-web-pro','payment')*11,'Rok = 11 miesiecy (abonament i jednorazowo)');
check(hosting_period_days('year')===365&&hosting_period_days('month')===30&&hosting_period_every(365)==='co rok'&&hosting_period_every(30)==='co 30 dni','Okres: dni i opisy');
$tz=false;try{hosting_checkout_spec('dnct-web-pro','subscription',true,'year');}catch(InvalidArgumentException $e){$tz=true;}
check($tz,'Test 7 dni niedostepny przy rocznym');
check(HOSTING_MANAGED_PRICE===2999,'Hosting w stronie pod opieka 29,99 zl');
fresh();check(checkout(input(['trial'=>true]))->status===422,'Brak zgody na abonament po trialu blokuje zamówienie');
check(checkout(input(['trial'=>true,'trial_accepted'=>true,'billing_mode'=>'payment']))->status===422,'Trial jednorazowy jest odrzucany');
check(checkout(input(['package'=>'dnct-pro']))->status===422,'Stare plany nie są sprzedawane jako nowa oferta');
fresh();$r=checkout(input(['trial'=>true,'trial_accepted'=>true]));$last=\Stripe\Checkout\Session::$calls[array_key_last(\Stripe\Checkout\Session::$calls)];
check($r->status===200&&$last[0]['subscription_data']['trial_period_days']===7&&$last[0]['payment_method_collection']==='always'&&$last[0]['payment_method_types']===['card'],'Trial 7 dni wymaga karty w Checkout');
check($last[0]['line_items'][0]['price_data']['recurring']===['interval'=>'day','interval_count'=>30],'Abonament obejmuje dokładnie 30 dni');
check(!empty($last[1]['idempotency_key']),'Idempotencja tworzenia sesji Stripe');
$orderId=$r->data['order_id'];$session=(object)['id'=>$db->orders[$orderId]['stripe_session_id'],'payment_status'=>'no_payment_required','customer'=>'cus_fake','subscription'=>'sub_fake'];
check(hosting_webhook_checkout_completed($db,$session),'Webhook uruchamia test bez opłaty');
check(abs(strtotime($db->orders[$orderId]['expires_at'])-(time()+7*86400))<3,'Test daje 7 dni dostępu, nie 30');
$before=$provisions;hosting_webhook_checkout_completed($db,$session);check($provisions===$before,'Powtórzony webhook nie uruchamia hostingu drugi raz');
$invoice=(object)['billing_reason'=>'subscription_cycle','period_end'=>time()+7*86400,'lines'=>(object)['data'=>[(object)['type'=>'subscription','period'=>(object)['end'=>time()+37*86400]]]]];
hosting_webhook_invoice_paid($db,$db->orders[$orderId],$invoice);check(abs(strtotime($db->orders[$orderId]['expires_at'])-(time()+37*86400))<3,'Pierwsza opłata po teście dodaje pełne 30 dni według pozycji faktury');
check(!hosting_trial_claim($db,'stable-account','changed@example.invalid',99),'Zmiana e-maila nie daje drugiego testu temu samemu kontu');
check(!hosting_trial_claim($db,'different-account','qa@example.invalid',100),'Ten sam adres nie dostaje drugiego testu');
try {eval(source($argv[1]));}catch(Reply $r){check($r->status===200,'Można wyłączyć odnawianie własnego hostingu');}
check(\Stripe\Subscription::$updates[0][1]['cancel_at_period_end']===true&&$db->orders[$orderId]['billing_mode']==='payment','Rezygnacja blokuje następną opłatę i zachowuje bieżący okres');
echo "Wszystkie scenariusze zakończone. Bez połączeń z bazą produkcyjną i Stripe.\n";
}
