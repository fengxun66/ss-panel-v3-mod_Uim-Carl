<?php

namespace App\Controllers;

use App\Services\Auth;
use App\Models\Node;
use App\Models\TrafficLog;
use App\Models\InviteCode;
use App\Models\CheckInLog;
use App\Models\Ann;
use App\Models\Speedtest;
use App\Models\Shop;
use App\Models\Coupon;
use App\Models\Bought;
use App\Models\Ticket;
use App\Services\Config;
use App\Services\Gateway\ChenPay;
use App\Services\Payment;
use App\Utils;
use App\Utils\AliPay;
use App\Utils\Hash;
use App\Utils\Tools;
use App\Utils\Radius;
use App\Utils\Wecenter;
use App\Models\RadiusBan;
use App\Models\DetectLog;
use App\Models\DetectRule;

use voku\helper\AntiXSS;

use App\Models\User;
use App\Models\Code;
use App\Models\Ip;
use App\Models\Paylist;
use App\Models\LoginIp;
use App\Models\BlockIp;
use App\Models\UnblockIp;
use App\Models\Payback;
use App\Models\Relay;
use App\Utils\QQWry;
use App\Utils\GA;
use App\Utils\Geetest;
use App\Utils\Telegram;
use App\Utils\TelegramSessionManager;
use App\Utils\Pay;
use App\Utils\URL;
use App\Services\Mail;

/**
 *  HomeController
 */
class UserController extends BaseController
{
    private $user;

    public function __construct()
    {
        $this->user = Auth::getUser();
    }

    public function index($request, $response, $args)
    {

        $user = $this->user;

        $ios_token = LinkController::GenerateIosCode("smart", 0, $this->user->id, 0, "smart");

        $acl_token = LinkController::GenerateAclCode("smart", 0, $this->user->id, 0, "smart");

        $router_token = LinkController::GenerateRouterCode($this->user->id, 0);
        $router_token_without_mu = LinkController::GenerateRouterCode($this->user->id, 1);

        $ssr_sub_token = LinkController::GenerateSSRSubCode($this->user->id, 0);

        $uid = time() . rand(1, 10000);
        if (Config::get('enable_geetest_checkin') == 'true') {
            $GtSdk = Geetest::get($uid);
        } else {
            $GtSdk = null;
        }

        $Ann = Ann::orderBy('date', 'desc')->first();

        return $this->view()->assign("ssr_sub_token", $ssr_sub_token)->assign("router_token", $router_token)
            ->assign("router_token_without_mu", $router_token_without_mu)->assign("acl_token", $acl_token)
            ->assign('ann', $Ann)->assign('geetest_html', $GtSdk)->assign("ios_token", $ios_token)
            ->assign('enable_duoshuo', Config::get('enable_duoshuo'))->assign('duoshuo_shortname', Config::get('duoshuo_shortname'))
            ->assign("user", $this->user)->registerClass("URL", "App\Utils\URL")->assign('baseUrl', Config::get('baseUrl'))->display('user/index.tpl');
    }


    public function panel($request, $response, $args)
    {

        $user = $this->user;

        $ios_token = LinkController::GenerateIosCode("smart", 0, $this->user->id, 0, "smart");

        $acl_token = LinkController::GenerateAclCode("smart", 0, $this->user->id, 0, "smart");

        $router_token = LinkController::GenerateRouterCode($this->user->id, 0);
        $router_token_without_mu = LinkController::GenerateRouterCode($this->user->id, 1);

        $ssr_sub_token = LinkController::GenerateSSRSubCode($this->user->id, 0);

        $uid = time() . rand(1, 10000);
        if (Config::get('enable_geetest_checkin') == 'true') {
            $GtSdk = Geetest::get($uid);
        } else {
            $GtSdk = null;
        }

        $Ann = Ann::orderBy('date', 'desc')->first();


        return $this->view()->assign("ssr_sub_token", $ssr_sub_token)->assign("router_token", $router_token)
            ->assign("router_token_without_mu", $router_token_without_mu)->assign("acl_token", $acl_token)
            ->assign('ann', $Ann)->assign('geetest_html', $GtSdk)->assign("ios_token", $ios_token)
            ->assign('enable_duoshuo', Config::get('enable_duoshuo'))->assign('duoshuo_shortname', Config::get('duoshuo_shortname'))
            ->assign("user", $this->user)->registerClass("URL", "App\Utils\URL")->assign('baseUrl', Config::get('baseUrl'))->display('user/panel.tpl');
    }

    public function lookingglass($request, $response, $args)
    {
        $Speedtest = Speedtest::where("datetime", ">", time() - Config::get('Speedtest_duration') * 3600)->orderBy('datetime', 'desc')->get();

        return $this->view()->assign('speedtest', $Speedtest)->assign('hour', Config::get('Speedtest_duration'))->display('user/lookingglass.tpl');
    }

    public function code($request, $response, $args)
    {
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $codes = Code::where('type', '<>', '-2')->where('userid', '=', $this->user->id)->orderBy('id', 'desc')->paginate(15, ['*'], 'page', $pageNum);
        $codes->setPath('/user/code');
        return $this->view()->assign('codes', $codes)->assign('pmw', Payment::purchaseHTML())->display('user/code.tpl');
    }

    public function orderDelete($request, $response, $args)
    {
        return (new ChenPay())->orderDelete($request);
    }

    public function donate($request, $response, $args)
    {
        if (Config::get('enable_donate') != 'true') {
            exit(0);
        }

        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $codes = Code::where(
            function ($query) {
                $query->where("type", "=", -1)
                    ->orWhere("type", "=", -2);
            }
        )->where("isused", 1)->orderBy('id', 'desc')->paginate(15, ['*'], 'page', $pageNum);
        $codes->setPath('/user/donate');
        return $this->view()->assign('codes', $codes)->assign('total_in', Code::where('isused', 1)->where('type', -1)->sum('number'))->assign('total_out', Code::where('isused', 1)->where('type', -2)->sum('number'))->display('user/donate.tpl');
    }

    function isHTTPS()
    {
        define('HTTPS', false);
        if (defined('HTTPS') && HTTPS) return true;
        if (!isset($_SERVER)) return FALSE;
        if (!isset($_SERVER['HTTPS'])) return FALSE;
        if ($_SERVER['HTTPS'] === 1) {  //Apache
            return TRUE;
        } elseif ($_SERVER['HTTPS'] === 'on') { //IIS
            return TRUE;
        } elseif ($_SERVER['SERVER_PORT'] == 443) { //??????
            return TRUE;
        }
        return FALSE;
    }



    public function code_check($request, $response, $args)
    {
        $time = $request->getQueryParams()["time"];
        $codes = Code::where('userid', '=', $this->user->id)->where('usedatetime', '>', date('Y-m-d H:i:s', $time))->first();
        if ($codes != null && strpos($codes->code, "??????") !== false) {
            $res['ret'] = 1;
            return $response->getBody()->write(json_encode($res));
        } else {
            $res['ret'] = 0;
            return $response->getBody()->write(json_encode($res));
        }
    }

    public function f2fpayget($request, $response, $args)
    {
        $time = $request->getQueryParams()["time"];
        $res['ret'] = 1;
        return $response->getBody()->write(json_encode($res));
    }

    public function f2fpay($request, $response, $args)
    {
        $amount = $request->getParam('amount');
        if ($amount == "") {
            $res['ret'] = 0;
            $res['msg'] = "?????????????????????" . $amount;
            return $response->getBody()->write(json_encode($res));
        }
        $user = $this->user;

        //???????????????
        $qrPayResult = Pay::alipay_get_qrcode($user, $amount, $qrPay);
        //  ?????????????????????????????????
        switch ($qrPayResult->getTradeStatus()) {
            case "SUCCESS":
                $aliresponse = $qrPayResult->getResponse();
                $res['ret'] = 1;
                $res['msg'] = "?????????????????????";
                $res['amount'] = $amount;
                $res['qrcode'] = $qrPay->create_erweima($aliresponse->qr_code);

                break;
            case "FAILED":
                $res['ret'] = 0;
                $res['msg'] = "????????????????????????????????????! ??????????????????????????????";

                break;
            case "UNKNOWN":
                $res['ret'] = 0;
                $res['msg'] = "???????????????????????????! ??????????????????????????????";

                break;
            default:
                $res['ret'] = 0;
                $res['msg'] = "?????????????????????????????????! ??????????????????????????????";

                break;
        }

        return $response->getBody()->write(json_encode($res));
    }

    public function alipay($request, $response, $args)
    {
        $amount = $request->getQueryParams()["amount"];
        Pay::getGen($this->user, $amount);
    }


    public function codepost($request, $response, $args)
    {
        $code = $request->getParam('code');
        $code = trim($code);
        $user = $this->user;

        if ($code == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $codeq = Code::where("code", "=", $code)->where("isused", "=", 0)->first();
        if ($codeq == null) {
            $res['ret'] = 0;
            $res['msg'] = "??????????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $codeq->isused = 1;
        $codeq->usedatetime = date("Y-m-d H:i:s");
        $codeq->userid = $user->id;
        $codeq->save();

        if ($codeq->type == -1) {
            $user->money = ($user->money + $codeq->number);
            $user->save();

            if ($user->ref_by != "" && $user->ref_by != 0 && $user->ref_by != null) {
                $gift_user = User::where("id", "=", $user->ref_by)->first();
                $gift_user->money = ($gift_user->money + ($codeq->number * (Config::get('code_payback') / 100)));
                $gift_user->save();

                $Payback = new Payback();
                $Payback->total = $codeq->number;
                $Payback->userid = $this->user->id;
                $Payback->ref_by = $this->user->ref_by;
                $Payback->ref_get = $codeq->number * (Config::get('code_payback') / 100);
                $Payback->datetime = time();
                $Payback->save();
            }

            $res['ret'] = 1;
            $res['msg'] = "?????????????????????????????????" . $codeq->number . "??????";

            if (Config::get('enable_donate') == 'true') {
                if ($this->user->is_hide == 1) {
                    Telegram::Send("?????????????????????????????????????????????????????????????????? " . $codeq->number . " ??????~");
                } else {
                    Telegram::Send("???????????????" . $this->user->user_name . " ???????????????????????? " . $codeq->number . " ??????~");
                }
            }

            return $response->getBody()->write(json_encode($res));
        }

        if ($codeq->type == 10001) {
            $user->transfer_enable = $user->transfer_enable + $codeq->number * 1024 * 1024 * 1024;
            $user->save();
        }

        if ($codeq->type == 10002) {
            if (time() > strtotime($user->expire_in)) {
                $user->expire_in = date("Y-m-d H:i:s", time() + $codeq->number * 86400);
            } else {
                $user->expire_in = date("Y-m-d H:i:s", strtotime($user->expire_in) + $codeq->number * 86400);
            }
            $user->save();
        }

        if ($codeq->type >= 1 && $codeq->type <= 10000) {
            if ($user->class == 0 || $user->class != $codeq->type) {
                $user->class_expire = date("Y-m-d H:i:s", time());
                $user->save();
            }
            $user->class_expire = date("Y-m-d H:i:s", strtotime($user->class_expire) + $codeq->number * 86400);
            $user->class = $codeq->type;
            $user->save();
        }
    }


    public function GaCheck($request, $response, $args)
    {
        $code = $request->getParam('code');
        $user = $this->user;


        if ($code == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $ga = new GA();
        $rcode = $ga->verifyCode($user->ga_token, $code);
        if (!$rcode) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }


        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $response->getBody()->write(json_encode($res));
    }


    public function GaSet($request, $response, $args)
    {
        $enable = $request->getParam('enable');
        $user = $this->user;


        if ($enable == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $user->ga_enable = $enable;
        $user->save();


        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $response->getBody()->write(json_encode($res));
    }

    public function ResetPort($request, $response, $args)
    {
        $price = Config::get('port_price');
        $user = $this->user;

        if ($user->money < $price) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $origin_port = $user->port;

        $user->port = Tools::getAvPort();


        $relay_rules = Relay::where('user_id', $user->id)->where('port', $origin_port)->get();
        foreach ($relay_rules as $rule) {
            $rule->port = $user->port;
            $rule->save();
        }

        $user->money -= $price;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = $user->port;
        return $response->getBody()->write(json_encode($res));
    }

    public function SpecifyPort($request, $response, $args)
    {
        $price = Config::get('port_price_specify');
        $user = $this->user;

        if ($user->money < $price) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $port = $request->getParam('port');

        if ($port < Config::get('min_port') || $port > Config::get('max_port') || Tools::isInt($port) == false) {
            $res['ret'] = 0;
            $res['msg'] = "???????????????????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $port_occupied = User::pluck('port')->toArray();

        if (in_array($port, $port_occupied) == true) {
            $res['ret'] = 0;
            $res['msg'] = "??????????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $origin_port = $user->port;

        $user->port = $port;


        $relay_rules = Relay::where('user_id', $user->id)->where('port', $origin_port)->get();
        foreach ($relay_rules as $rule) {
            $rule->port = $user->port;
            $rule->save();
        }

        $user->money -= $price;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $response->getBody()->write(json_encode($res));
    }

    public function GaReset($request, $response, $args)
    {
        $user = $this->user;
        $ga = new GA();
        $secret = $ga->createSecret();

        $user->ga_token = $secret;
        $user->save();
        $newResponse = $response->withStatus(302)->withHeader('Location', '/user/edit');
        return $newResponse;
    }


    public function nodeAjax($request, $response, $args)
    {
        $id = $args['id'];
        $point_node = Node::find($id);
        $prefix = explode(" - ", $point_node->name);
        return $this->view()->assign('point_node', $point_node)->assign('prefix', $prefix[0])->assign('id', $id)->display('user/nodeajax.tpl');
    }


    public function node($request, $response, $args)
    {
        $user = Auth::getUser();
        $nodes = Node::where('type', 1)->orderBy('node_class')->orderBy('name')->get();
        $relay_rules = Relay::where('user_id', $this->user->id)->orwhere('user_id', 0)->orderBy('id', 'asc')->get();
		if (!Tools::is_protocol_relay($user)) {
            $relay_rules = array();
        }

		$array_nodes=array();
		$nodes_muport = array();

		foreach($nodes as $node){
			if($node->node_group!=$user->node_group && $node->node_group!=0){
				continue;
			}
			if ($node->sort == 9) {
                $mu_user = User::where('port', '=', $node->server)->first();
                $mu_user->obfs_param = $this->user->getMuMd5();
                array_push($nodes_muport, array('server' => $node, 'user' => $mu_user));
                continue;
            }
			$array_node=array();

			$array_node['id']=$node->id;
			$array_node['class']=$node->node_class;
            $array_node['name']=$node->name;
            if ($node->sort == 13) {
                $server = explode(';', $node->server);
                $array_node['server']=$server[1];
            } else {
                $array_node['server']=$node->server;
            }
			$array_node['sort']=$node->sort;
			$array_node['info']=$node->info;
			$array_node['mu_only']=$node->mu_only;
			$array_node['group']=$node->node_group;

            $array_node['raw_node'] = $node;
			$regex = Config::get('flag_regex');
            $matches = array();
            preg_match($regex, $node->name, $matches);
            if (isset($matches[0])) {
				$array_node['flag'] = $matches[0].'.png';
            }
			else {
                $array_node['flag'] = 'unknown.png';
            }

			$node_online=$node->isNodeOnline();
			if($node_online===null){
				$array_node['online']=0;
			}
			else if($node_online===true){
				$array_node['online']=1;
			}
			else if($node_online===false){
				$array_node['online']=-1;
			}

            if (in_array($node->sort, array(0, 7, 8, 10, 11, 12, 13))) {
                $array_node['online_user']=$node->getOnlineUserCount();
            } else {
                $array_node['online_user']=-1;
            }

			$nodeLoad = $node->getNodeLoad();
            if (isset($nodeLoad[0]['load'])) {
                $array_node['latest_load'] = ((explode(" ", $nodeLoad[0]['load']))[0]) * 100;
            }
			else {
                $array_node['latest_load'] = -1;
            }

            $array_node['traffic_used'] = (int)Tools::flowToGB($node->node_bandwidth);
            $array_node['traffic_limit'] = (int)Tools::flowToGB($node->node_bandwidth_limit);
			if($node->node_speedlimit==0.0){
				$array_node['bandwidth']=0;
			}
			else if($node->node_speedlimit>=1024.00){
				$array_node['bandwidth']=round($node->node_speedlimit/1024.00,1).'Gbps';
			}
			else{
				$array_node['bandwidth']=$node->node_speedlimit.'Mbps';
			}

			$array_node['traffic_rate']=$node->traffic_rate;
			$array_node['status']=$node->status;

			array_push($array_nodes,$array_node);
		}
		return $this->view()->assign('nodes', $array_nodes)->assign('nodes_muport', $nodes_muport)->assign('relay_rules', $relay_rules)->assign('tools', new Tools())->assign('user', $user)->registerClass("URL", "App\Utils\URL")->display('user/node.tpl');
	}


    public function nodeInfo($request, $response, $args)
    {
        $user = Auth::getUser();
        $id = $args['id'];
        $mu = $request->getQueryParams()["ismu"];
        $relay_rule_id = $request->getQueryParams()["relay_rule"];
        $node = Node::find($id);

        if ($node == null) {
            return null;
        }


        switch ($node->sort) {

            case 0:
                if ((($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) || $user->is_admin) && ($node->node_bandwidth_limit == 0 || $node->node_bandwidth < $node->node_bandwidth_limit)) {
                    return $this->view()->assign('node', $node)->assign('user', $user)->assign('mu', $mu)->assign('relay_rule_id', $relay_rule_id)->registerClass("URL", "App\Utils\URL")->display('user/nodeinfo.tpl');
                }
                break;

            case 1:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);
                    $json_show = "VPN ??????<br>?????????" . $node->server . "<br>" . "????????????" . $email . "<br>?????????" . $this->user->passwd . "<br>???????????????" . $node->method . "<br>?????????" . $node->info;

                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfovpn.tpl');
                }
                break;

            case 2:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);
                    $json_show = "SSH ??????<br>?????????" . $node->server . "<br>" . "????????????" . $email . "<br>?????????" . $this->user->passwd . "<br>???????????????" . $node->method . "<br>?????????" . $node->info;

                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfossh.tpl');
                }

                break;


            case 3:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);
                    $exp = explode(":", $node->server);
                    $token = LinkController::GenerateCode(3, $exp[0], $exp[1], 0, $this->user->id);
                    $json_show = "PAC ??????<br>?????????" . Config::get('baseUrl') . "/link/" . $token . "<br>" . "????????????" . $email . "<br>?????????" . $this->user->passwd . "<br>???????????????" . $node->method . "<br>?????????" . $node->info;

                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfopac.tpl');
                }

                break;

            case 4:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);
                    $json_show = "APN ??????<br>???????????????" . $node->server . "<br>" . "????????????" . $email . "<br>?????????" . $this->user->passwd . "<br>???????????????" . $node->method . "<br>?????????" . $node->info;

                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfoapn.tpl');
                }

                break;

            case 5:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);

                    $json_show = "Anyconnect ??????<br>?????????" . $node->server . "<br>" . "????????????" . $email . "<br>?????????" . $this->user->passwd . "<br>???????????????" . $node->method . "<br>?????????" . $node->info;

                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfoanyconnect.tpl');
                }


                break;

            case 6:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);
                    $exp = explode(":", $node->server);

                    $token_cmcc = LinkController::GenerateApnCode("cmnet", $exp[0], $exp[1], $this->user->id);
                    $token_cnunc = LinkController::GenerateApnCode("3gnet", $exp[0], $exp[1], $this->user->id);
                    $token_ctnet = LinkController::GenerateApnCode("ctnet", $exp[0], $exp[1], $this->user->id);

                    $json_show = "APN ??????<br>???????????????" . Config::get('baseUrl') . "/link/" . $token_cmcc . "<br>???????????????" . Config::get('baseUrl') . "/link/" . $token_cnunc . "<br>???????????????" . Config::get('baseUrl') . "/link/" . $token_ctnet . "<br>" . "????????????" . $email . "<br>?????????" . $this->user->passwd . "<br>???????????????" . $node->method . "<br>?????????" . $node->info;

                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfoapndownload.tpl');
                }


                break;

            case 7:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);
                    $token = LinkController::GenerateCode(7, $node->server, ($this->user->port - 20000), 0, $this->user->id);
                    $json_show = "PAC Plus ??????<br>PAC ?????????" . Config::get('baseUrl') . "/link/" . $token . "<br>???????????????" . $node->method . "<br>?????????" . $node->info;


                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfopacplus.tpl');
                }


                break;

            case 8:
                if ($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) {
                    $email = $this->user->email;
                    $email = Radius::GetUserName($email);
                    $token = LinkController::GenerateCode(8, $node->server, ($this->user->port - 20000), 0, $this->user->id);
                    $token_ios = LinkController::GenerateCode(8, $node->server, ($this->user->port - 20000), 1, $this->user->id);
                    $json_show = "PAC Plus Plus??????<br>PAC ???????????????" . Config::get('baseUrl') . "/link/" . $token . "<br>PAC iOS ?????????" . Config::get('baseUrl') . "/link/" . $token_ios . "<br>" . "?????????" . $node->info;

                    return $this->view()->assign('json_show', $json_show)->display('user/nodeinfopacpp.tpl');
                }


                break;


            case 10:
                if ((($user->class >= $node->node_class && ($user->node_group == $node->node_group || $node->node_group == 0)) || $user->is_admin) && ($node->node_bandwidth_limit == 0 || $node->node_bandwidth < $node->node_bandwidth_limit)) {
                    return $this->view()->assign('node', $node)->assign('user', $user)->assign('mu', $mu)->assign('relay_rule_id', $relay_rule_id)->registerClass("URL", "App\Utils\URL")->display('user/nodeinfo.tpl');
                }
                break;
            default:
                echo "??????";

        }
    }

    public function GetPcConf($request, $response, $args)
    {
        $is_mu = $request->getQueryParams()["is_mu"];
        $is_ss = $request->getQueryParams()["is_ss"];

        $newResponse = $response->withHeader('Content-type', ' application/octet-stream')->withHeader('Content-Disposition', ' attachment; filename=gui-config.json');//->getBody()->write($builder->output());
        $newResponse->getBody()->write(LinkController::GetPcConf($this->user, $is_mu, $is_ss));

        return $newResponse;
    }

    public function GetIosConf($request, $response, $args)
    {
        $newResponse = $response->withHeader('Content-type', ' application/octet-stream')->withHeader('Content-Disposition', ' attachment; filename=allinone.conf');//->getBody()->write($builder->output());
        if ($this->user->is_admin) {
            $newResponse->getBody()->write(LinkController::GetIosConf(Node::where(
                function ($query) {
                    $query->where('sort', 0)
                        ->orWhere('sort', 10);
                }
            )->where("type", "1")->get(), $this->user));
        } else {
            $newResponse->getBody()->write(LinkController::GetIosConf(Node::where(
                function ($query) {
                    $query->where('sort', 0)
                        ->orWhere('sort', 10);
                }
            )->where("type", "1")->where(
                function ($query) {
                    $query->where("node_group", "=", $this->user->node_group)
                        ->orWhere("node_group", "=", 0);
                }
            )->where("node_class", "<=", $this->user->class)->get(), $this->user));
        }
        return $newResponse;
    }


    public function profile($request, $response, $args)
    {
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $paybacks = Payback::where("ref_by", $this->user->id)->orderBy("datetime", "desc")->paginate(15, ['*'], 'page', $pageNum);
        $paybacks->setPath('/user/profile');

        $iplocation = new QQWry();

        $userip = array();

        $total = Ip::where("datetime", ">=", time() - 300)->where('userid', '=', $this->user->id)->get();

        $totallogin = LoginIp::where('userid', '=', $this->user->id)->where("type", "=", 0)->orderBy("datetime", "desc")->take(10)->get();

        $userloginip = array();

        foreach ($totallogin as $single) {
            //if(isset($useripcount[$single->userid]))
            {
                if (!isset($userloginip[$single->ip])) {
                    //$useripcount[$single->userid]=$useripcount[$single->userid]+1;
                    $location = $iplocation->getlocation($single->ip);
                    $userloginip[$single->ip] = iconv('gbk', 'utf-8//IGNORE', $location['country'] . $location['area']);
                }
            }
        }

        foreach ($total as $single) {
            //if(isset($useripcount[$single->userid]))
            {
                $single->ip = Tools::getRealIp($single->ip);
                $is_node = Node::where("node_ip", $single->ip)->first();
                if ($is_node) {
                    continue;
                }


                if (!isset($userip[$single->ip])) {
                    //$useripcount[$single->userid]=$useripcount[$single->userid]+1;
                    $location = $iplocation->getlocation($single->ip);
                    $userip[$single->ip] = iconv('gbk', 'utf-8//IGNORE', $location['country'] . $location['area']);
                }
            }
        }


        return $this->view()->assign("userip", $userip)->assign("userloginip", $userloginip)->assign("paybacks", $paybacks)->display('user/profile.tpl');
    }


    public function announcement($request, $response, $args)
    {
        $Anns = Ann::orderBy('date', 'desc')->get();


        return $this->view()->assign("anns", $Anns)->display('user/announcement.tpl');
    }


    public function edit($request, $response, $args)
    {
        $themes = Tools::getDir(BASE_PATH . "/resources/views");

        $BIP = BlockIp::where("ip", $_SERVER["REMOTE_ADDR"])->first();
        if ($BIP == null) {
            $Block = "IP: " . $_SERVER["REMOTE_ADDR"] . " ????????????";
            $isBlock = 0;
        } else {
            $Block = "IP: " . $_SERVER["REMOTE_ADDR"] . " ?????????";
            $isBlock = 1;
        }

        $bind_token = TelegramSessionManager::add_bind_session($this->user);

        $config_service = new Config();

        return $this->view()->assign('user', $this->user)->assign('themes', $themes)->assign('isBlock', $isBlock)->assign('Block', $Block)->assign('bind_token', $bind_token)->assign('telegram_bot', Config::get('telegram_bot'))->assign('config_service', $config_service)
            ->registerClass("URL", "App\Utils\URL")->display('user/edit.tpl');
    }


    public function invite($request, $response, $args)
    {
        /*$pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $codes=InviteCode::where('user_id', $this->user->id)->orderBy("created_at", "desc")->paginate(15, ['*'], 'page', $pageNum);
        $codes->setPath('/user/invite');*/
        $code = InviteCode::where('user_id', $this->user->id)->first();
        if ($code == null) {
            $char = Tools::genRandomChar(32);
            $code = new InviteCode();
            $code->code = $char;
            $code->user_id = $this->user->id;
            $code->save();
        }

        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $paybacks = Payback::where("ref_by", $this->user->id)->orderBy("id", "desc")->paginate(15, ['*'], 'page', $pageNum);
        if (!$paybacks_sum = Payback::where("ref_by", $this->user->id)->sum('ref_get')) {
            $paybacks_sum = 0;
        }
        $paybacks->setPath('/user/invite');

        return $this->view()->assign('code', $code)->assign('paybacks', $paybacks)->assign('paybacks_sum', $paybacks_sum)->display('user/invite.tpl');
    }

    //??????????????????
    public function doInvite($request, $response, $args)
    {
        $n = $this->user->invite_num;
        if ($n < 1) {
            $res['ret'] = 0;
            $res['msg'] = "??????";
            return $response->getBody()->write(json_encode($res));
        }
        for ($i = 0; $i < $n; $i++) {
            $char = Tools::genRandomChar(32);
            $code = new InviteCode();
            $code->code = $char;
            $code->user_id = $this->user->id;
            $code->save();
        }
        $this->user->invite_num = 0;
        $this->user->save();
        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }

    public function buyInvite($request, $response, $args)
    {
        $price = Config::get('invite_price');
        $num = $request->getParam('num');
        $num = trim($num);

        if (Tools::isInt($num) == false || $price < 0 || $num <= 0) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $amount = $price * $num;

        $user = $this->user;
        if ($user->money < $amount) {
            $res['ret'] = 0;
            $res['msg'] = "????????????????????????" . $amount . "??????";
            return $response->getBody()->write(json_encode($res));
        }
        $user->invite_num += $num;
        $user->money -= $amount;
        $user->save();
        $res['ret'] = 1;
        $res['msg'] = "????????????????????????";
        return $response->getBody()->write(json_encode($res));
    }

    public function sys()
    {
        return $this->view()->assign('ana', "")->display('user/sys.tpl');
    }

    public function updatePassword($request, $response, $args)
    {
        $oldpwd = $request->getParam('oldpwd');
        $pwd = $request->getParam('pwd');
        $repwd = $request->getParam('repwd');
        $user = $this->user;
        if (!Hash::checkPassword($user->pass, $oldpwd)) {
            $res['ret'] = 0;
            $res['msg'] = "???????????????";
            return $response->getBody()->write(json_encode($res));
        }
        if ($pwd != $repwd) {
            $res['ret'] = 0;
            $res['msg'] = "?????????????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if (strlen($pwd) < 8) {
            $res['ret'] = 0;
            $res['msg'] = "???????????????";
            return $response->getBody()->write(json_encode($res));
        }
        $hashPwd = Hash::passwordHash($pwd);
        $user->pass = $hashPwd;
        $user->save();

        $user->clean_link();

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }

    public function updateHide($request, $response, $args)
    {
        $hide = $request->getParam('hide');
        $user = $this->user;
        $user->is_hide = $hide;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }

    public function Unblock($request, $response, $args)
    {
        $user = $this->user;
        $BIP = BlockIp::where("ip", $_SERVER["REMOTE_ADDR"])->get();
        foreach ($BIP as $bi) {
            $bi->delete();
        }

        $UIP = new UnblockIp();
        $UIP->userid = $user->id;
        $UIP->ip = $_SERVER["REMOTE_ADDR"];
        $UIP->datetime = time();
        $UIP->save();


        $res['ret'] = 1;
        $res['msg'] = $_SERVER["REMOTE_ADDR"];
        return $this->echoJson($response, $res);
    }

    public function shop($request, $response, $args)
    {
        $shops = Shop::where("status", 1)->orderBy("name")->get();
        return $this->view()->assign('shops', $shops)->display('user/shop.tpl');
    }

    public function CouponCheck($request, $response, $args)
    {
        $coupon = $request->getParam('coupon');
        $coupon = trim($coupon);

        $user = $this->user;

        if (!$user->isLogin) {
            $res['ret'] = -1;
            return $response->getBody()->write(json_encode($res));
        }

        $shop = $request->getParam('shop');

        $shop = Shop::where("id", $shop)->where("status", 1)->first();

        if ($shop == null) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if ($coupon == "") {
            $res['ret'] = 1;
            $res['name'] = $shop->name;
            $res['credit'] = "0 %";
            $res['total'] = $shop->price . "???";
            return $response->getBody()->write(json_encode($res));
        }

        $coupon = Coupon::where("code", $coupon)->first();

        if ($coupon == null) {
            $res['ret'] = 0;
            $res['msg'] = "???????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if ($coupon->order($shop->id) == false) {
            $res['ret'] = 0;
            $res['msg'] = "?????????????????????????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $use_limit = $coupon->onetime;
        if ($use_limit > 0) {
            $use_count = Bought::where("userid", $user->id)->where("coupon", $coupon->code)->count();
            if ($use_count >= $use_limit) {
                $res['ret'] = 0;
                $res['msg'] = "????????????????????????";
                return $response->getBody()->write(json_encode($res));
            }
        }

        $res['ret'] = 1;
        $res['name'] = $shop->name;
        $res['credit'] = $coupon->credit . " %";
        $res['total'] = $shop->price * ((100 - $coupon->credit) / 100) . "???";

        return $response->getBody()->write(json_encode($res));
    }

    public function buy($request, $response, $args)
    {
        $coupon = $request->getParam('coupon');
        $coupon = trim($coupon);
        $code = $coupon;
        $shop = $request->getParam('shop');
        $disableothers = $request->getParam('disableothers');
        $autorenew = $request->getParam('autorenew');

        $shop = Shop::where("id", $shop)->where("status", 1)->first();

        if ($shop == null) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if ($coupon == "") {
            $credit = 0;
        } else {
            $coupon = Coupon::where("code", $coupon)->first();

            if ($coupon == null) {
                $credit = 0;
            } else {
                if ($coupon->onetime == 1) {
                    $onetime = true;
                }

                $credit = $coupon->credit;
            }

            if ($coupon->order($shop->id) == false) {
                $res['ret'] = 0;
                $res['msg'] = "?????????????????????????????????";
                return $response->getBody()->write(json_encode($res));
            }

            if ($coupon->expire < time()) {
                $res['ret'] = 0;
                $res['msg'] = "?????????????????????";
                return $response->getBody()->write(json_encode($res));
            }
        }

        $price = $shop->price * ((100 - $credit) / 100);
        $user = $this->user;

        if ($user->money < $price) {
            $res['ret'] = 0;
            $res['msg'] = '?????????~ ??????????????????????????????' . $price . '??????</br><a href="/user/code">????????????????????????</a>';
            return $response->getBody()->write(json_encode($res));
        }

        $user->money = $user->money - $price;
        $user->save();

        if ($disableothers == 1) {
            $boughts = Bought::where("userid", $user->id)->get();
            foreach ($boughts as $disable_bought) {
                $disable_bought->renew = 0;
                $disable_bought->save();
            }
        }

        $bought = new Bought();
        $bought->userid = $user->id;
        $bought->shopid = $shop->id;
        $bought->datetime = time();
        if ($autorenew == 0 || $shop->auto_renew == 0) {
            $bought->renew = 0;
        } else {
            $bought->renew = time() + $shop->auto_renew * 86400;
        }

        $bought->coupon = $code;


        if (isset($onetime)) {
            $price = $shop->price;
        }
        $bought->price = $price;
        $bought->save();

        $shop->buy($user);

        $res['ret'] = 1;
        $res['msg'] = "????????????";

        return $response->getBody()->write(json_encode($res));
    }

    public function bought($request, $response, $args)
    {
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $shops = Bought::where("userid", $this->user->id)->orderBy("id", "desc")->paginate(15, ['*'], 'page', $pageNum);
        $shops->setPath('/user/bought');

        return $this->view()->assign('shops', $shops)->display('user/bought.tpl');
    }

    public function deleteBoughtGet($request, $response, $args)
    {
        $id = $request->getParam('id');
        $shop = Bought::where("id", $id)->where("userid", $this->user->id)->first();

        if ($shop == null) {
            $rs['ret'] = 0;
            $rs['msg'] = "?????????????????????????????????????????????";
            return $response->getBody()->write(json_encode($rs));
        }

        if ($this->user->id == $shop->userid) {
            $shop->renew = 0;
        }

        if (!$shop->save()) {
            $rs['ret'] = 0;
            $rs['msg'] = "????????????????????????";
            return $response->getBody()->write(json_encode($rs));
        }
        $rs['ret'] = 1;
        $rs['msg'] = "????????????????????????";
        return $response->getBody()->write(json_encode($rs));
    }


    public function ticket($request, $response, $args)
    {
        if (Config::get('enable_ticket') != 'true') {
            exit(0);
        }
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $tickets = Ticket::where("userid", $this->user->id)->where("rootid", 0)->orderBy("datetime", "desc")->paginate(15, ['*'], 'page', $pageNum);
        $tickets->setPath('/user/ticket');

        return $this->view()->assign('tickets', $tickets)->display('user/ticket.tpl');
    }

    public function ticket_create($request, $response, $args)
    {
        return $this->view()->display('user/ticket_create.tpl');
    }

    public function ticket_add($request, $response, $args)
    {
        $title = $request->getParam('title');
        $content = $request->getParam('content');


        if ($title == "" || $content == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $this->echoJson($response, $res);
        }

        if (strpos($content, "admin") != false || strpos($content, "user") != false) {
            $res['ret'] = 0;
            $res['msg'] = "????????????????????????";
            return $this->echoJson($response, $res);
        }


        $ticket = new Ticket();

        $antiXss = new AntiXSS();

        $ticket->title = $antiXss->xss_clean($title);
        $ticket->content = $antiXss->xss_clean($content);
        $ticket->rootid = 0;
        $ticket->userid = $this->user->id;
        $ticket->datetime = time();
        $ticket->save();

        $adminUser = User::where("is_admin", "=", "1")->get();
        foreach ($adminUser as $user) {
            $subject = Config::get('appName') . "-??????????????????";
            $to = $user->email;
            $text = "?????????????????????????????????????????????????????????????????????";
            try {
                Mail::send($to, $subject, 'news/warn.tpl', [
                    "user" => $user, "text" => $text
                ], [
                ]);
            } catch (\Exception $e) {
                echo $e->getMessage();
            }
        }

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }

    public function ticket_update($request, $response, $args)
    {
        $id = $args['id'];
        $content = $request->getParam('content');
        $status = $request->getParam('status');

        if ($content == "" || $status == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $this->echoJson($response, $res);
        }

        if (strpos($content, "admin") != false || strpos($content, "user") != false) {
            $res['ret'] = 0;
            $res['msg'] = "????????????????????????";
            return $this->echoJson($response, $res);
        }


        $ticket_main = Ticket::where("id", "=", $id)->where("rootid", "=", 0)->first();
        if ($ticket_main->userid != $this->user->id) {
            $newResponse = $response->withStatus(302)->withHeader('Location', '/user/ticket');
            return $newResponse;
        }

        if ($status == 1 && $ticket_main->status != $status) {
            $adminUser = User::where("is_admin", "=", "1")->get();
            foreach ($adminUser as $user) {
                $subject = Config::get('appName') . "-?????????????????????";
                $to = $user->email;
                $text = "???????????????????????????????????????<a href=\"" . Config::get('baseUrl') . "/admin/ticket/" . $ticket_main->id . "/view\">??????</a>????????????????????????";
                try {
                    Mail::send($to, $subject, 'news/warn.tpl', [
                        "user" => $user, "text" => $text
                    ], [
                    ]);
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
            }
        } else {
            $adminUser = User::where("is_admin", "=", "1")->get();
            foreach ($adminUser as $user) {
                $subject = Config::get('appName') . "-???????????????";
                $to = $user->email;
                $text = "?????????????????????????????????<a href=\"" . Config::get('baseUrl') . "/admin/ticket/" . $ticket_main->id . "/view\">??????</a>????????????????????????";
                try {
                    Mail::send($to, $subject, 'news/warn.tpl', [
                        "user" => $user, "text" => $text
                    ], [
                    ]);
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
            }
        }

        $antiXss = new AntiXSS();

        $ticket = new Ticket();
        $ticket->title = $antiXss->xss_clean($ticket_main->title);
        $ticket->content = $antiXss->xss_clean($content);
        $ticket->rootid = $ticket_main->id;
        $ticket->userid = $this->user->id;
        $ticket->datetime = time();
        $ticket_main->status = $status;

        $ticket_main->save();
        $ticket->save();


        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }

    public function ticket_view($request, $response, $args)
    {
        $id = $args['id'];
        $ticket_main = Ticket::where("id", "=", $id)->where("rootid", "=", 0)->first();
        if ($ticket_main->userid != $this->user->id) {
            $newResponse = $response->withStatus(302)->withHeader('Location', '/user/ticket');
            return $newResponse;
        }

        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }


        $ticketset = Ticket::where("id", $id)->orWhere("rootid", "=", $id)->orderBy("datetime", "desc")->paginate(5, ['*'], 'page', $pageNum);
        $ticketset->setPath('/user/ticket/' . $id . "/view");


        return $this->view()->assign('ticketset', $ticketset)->assign("id", $id)->display('user/ticket_view.tpl');
    }


    public function updateWechat($request, $response, $args)
    {
        $type = $request->getParam('imtype');
        $wechat = $request->getParam('wechat');
        $wechat = trim($wechat);

        $user = $this->user;

        if ($user->telegram_id != 0) {
            $res['ret'] = 0;
            $res['msg'] = "???????????? Telegram ????????????????????????????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if ($wechat == "" || $type == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $user1 = User::where('im_value', $wechat)->where('im_type', $type)->first();
        if ($user1 != null) {
            $res['ret'] = 0;
            $res['msg'] = "??????????????????????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $user->im_type = $type;
        $antiXss = new AntiXSS();
        $user->im_value = $antiXss->xss_clean($wechat);
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }


    public function updateSSR($request, $response, $args)
    {
        $protocol = $request->getParam('protocol');
        $obfs = $request->getParam('obfs');
        $obfs_param = $request->getParam('obfs_param');
        $obfs_param = trim($obfs_param);

        $user = $this->user;

        if ($obfs == "" || $protocol == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if (!Tools::is_param_validate('obfs', $obfs)) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if (!Tools::is_param_validate('protocol', $protocol)) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $antiXss = new AntiXSS();

        $user->protocol = $antiXss->xss_clean($protocol);
        $user->obfs = $antiXss->xss_clean($obfs);
        $user->obfs_param = $antiXss->xss_clean($obfs_param);

        if (!Tools::checkNoneProtocol($user)) {
            $res['ret'] = 0;
            $res['msg'] = "??????????????????????????????????????????????????? none ??????????????????????????????????????????????????????<br>" . implode(',', Config::getSupportParam('allow_none_protocol')) . '<br>????????????????????????????????????????????????????????????????????????';
            return $this->echoJson($response, $res);
        }

        if (!URL::SSCanConnect($user) && !URL::SSRCanConnect($user)) {
            $res['ret'] = 0;
            $res['msg'] = "????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????";
            return $this->echoJson($response, $res);
        }

        $user->save();

        if (!URL::SSCanConnect($user)) {
            $res['ret'] = 1;
            $res['msg'] = "??????????????????????????????????????????????????????????????????????????? Shadowsocks??????????????????????????????????????????????????? ShadowsocksR ????????????";
            return $this->echoJson($response, $res);
        }

        if (!URL::SSRCanConnect($user)) {
            $res['ret'] = 1;
            $res['msg'] = "??????????????????????????????????????????????????????????????????????????? ShadowsocksR ????????????????????????????????????????????? Shadowsocks ????????????";
            return $this->echoJson($response, $res);
        }

        $res['ret'] = 1;
        $res['msg'] = "??????????????????????????????????????????????????????";
        return $this->echoJson($response, $res);
    }

    public function updateTheme($request, $response, $args)
    {
        $theme = $request->getParam('theme');

        $user = $this->user;

        if ($theme == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }


        $user->theme = filter_var($theme, FILTER_SANITIZE_STRING);
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }


    public function updateMail($request, $response, $args)
    {
        $mail = $request->getParam('mail');
        $mail = trim($mail);
        $user = $this->user;

        if (!($mail == "1" || $mail == "0")) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }


        $user->sendDailyMail = $mail;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }

    public function PacSet($request, $response, $args)
    {
        $pac = $request->getParam('pac');

        $user = $this->user;

        if ($pac == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }


        $user->pac = $pac;
        $user->save();

        $res['ret'] = 1;
        $res['msg'] = "????????????";
        return $this->echoJson($response, $res);
    }


    public function updateSsPwd($request, $response, $args)
    {
        $user = Auth::getUser();
        $pwd = $request->getParam('sspwd');
        $pwd = trim($pwd);

        if ($pwd == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if (!Tools::is_validate($pwd)) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $user->updateSsPwd($pwd);
        $res['ret'] = 1;


        Radius::Add($user, $pwd);


        return $this->echoJson($response, $res);
    }

    public function updateMethod($request, $response, $args)
    {
        $user = Auth::getUser();
        $method = $request->getParam('method');
        $method = strtolower($method);

        if ($method == "") {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        if (!Tools::is_param_validate('method', $method)) {
            $res['ret'] = 0;
            $res['msg'] = "????????????";
            return $response->getBody()->write(json_encode($res));
        }

        $user->method = $method;

        if (!Tools::checkNoneProtocol($user)) {
            $res['ret'] = 0;
            $res['msg'] = "????????????????????????????????????????????????????????? none ???????????????????????????????????????<br>" . implode(',', Config::getSupportParam('allow_none_protocol')) . '<br>??????????????????????????????????????????????????????????????????';
            return $this->echoJson($response, $res);
        }

        if (!URL::SSCanConnect($user) && !URL::SSRCanConnect($user)) {
            $res['ret'] = 0;
            $res['msg'] = "????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????";
            return $this->echoJson($response, $res);
        }

        $user->updateMethod($method);

        if (!URL::SSCanConnect($user)) {
            $res['ret'] = 0;
            $res['msg'] = "??????????????????????????????????????????????????????????????????????????? Shadowsocks??????????????????????????????????????????????????? ShadowsocksR ????????????";
            return $this->echoJson($response, $res);
        }

        if (!URL::SSRCanConnect($user)) {
            $res['ret'] = 1;
            $res['msg'] = "??????????????????????????????????????????????????????????????????????????? ShadowsocksR ????????????????????????????????????????????? Shadowsocks ????????????";
            return $this->echoJson($response, $res);
        }

        $res['ret'] = 1;
        $res['msg'] = "??????????????????????????????????????????????????????????????????";
        return $this->echoJson($response, $res);
    }

    public function logout($request, $response, $args)
    {
        Auth::logout();
        $newResponse = $response->withStatus(302)->withHeader('Location', '/auth/login');
        return $newResponse;
    }

    public function doCheckIn($request, $response, $args)
    {
        if (Config::get('enable_geetest_checkin') == 'true') {
            $ret = Geetest::verify($request->getParam('geetest_challenge'), $request->getParam('geetest_validate'), $request->getParam('geetest_seccode'));
            if (!$ret) {
                $res['ret'] = 0;
                $res['msg'] = "??????????????????????????????????????????????????????????????????";
                return $response->getBody()->write(json_encode($res));
            }
        }

		if(strtotime($this->user->expire_in) < time()){
		    $res['msg'] = "???????????????????????????????????????";
            $res['ret'] = 1;
            return $response->getBody()->write(json_encode($res));
		}

        if (!$this->user->isAbleToCheckin()) {
            $res['msg'] = "???????????????????????????...";
            $res['ret'] = 1;
            return $response->getBody()->write(json_encode($res));
        }
        $traffic = rand(Config::get('checkinMin'), Config::get('checkinMax'));
        $this->user->transfer_enable = $this->user->transfer_enable + Tools::toMB($traffic);
        $this->user->last_check_in_time = time();
        $this->user->save();
        $res['msg'] = sprintf("????????? %d MB??????.", $traffic);
        $res['ret'] = 1;
        return $this->echoJson($response, $res);
    }

    public function kill($request, $response, $args)
    {
        return $this->view()->display('user/kill.tpl');
    }

    public function handleKill($request, $response, $args)
    {
        $user = Auth::getUser();

        $email = $user->email;

        $passwd = $request->getParam('passwd');
        // check passwd
        $res = array();
        if (!Hash::checkPassword($user->pass, $passwd)) {
            $res['ret'] = 0;
            $res['msg'] = " ????????????";
            return $this->echoJson($response, $res);
        }
		        
        if (Config::get('enable_kill') == 'true') {
            Auth::logout();
            $user->kill_user();
            $res['ret'] = 1;
            $res['msg'] = "??????????????????????????????????????????????????????????????????!";
        } 
		else {
            $res['ret'] = 0;
            $res['msg'] = "????????????????????????????????????????????????????????????";
        }          
        return $this->echoJson($response, $res);
    }

    public function trafficLog($request, $response, $args)
    {
        $traffic = TrafficLog::where('user_id', $this->user->id)->where("log_time", ">", (time() - 3 * 86400))->orderBy('id', 'desc')->get();
        return $this->view()->assign('logs', $traffic)->display('user/trafficlog.tpl');
    }


    public function detect_index($request, $response, $args)
    {
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $logs = DetectRule::paginate(15, ['*'], 'page', $pageNum);
        $logs->setPath('/user/detect');
        return $this->view()->assign('rules', $logs)->display('user/detect_index.tpl');
    }

    public function detect_log($request, $response, $args)
    {
        $pageNum = 1;
        if (isset($request->getQueryParams()["page"])) {
            $pageNum = $request->getQueryParams()["page"];
        }
        $logs = DetectLog::orderBy('id', 'desc')->where('user_id', $this->user->id)->paginate(15, ['*'], 'page', $pageNum);
        $logs->setPath('/user/detect/log');
        return $this->view()->assign('logs', $logs)->display('user/detect_log.tpl');
    }

    public function disable($request, $response, $args)
    {
        return $this->view()->display('user/disable.tpl');
    }

    public function telegram_reset($request, $response, $args)
    {
        $user = $this->user;
        $user->telegram_id = 0;
        $user->save();
        $newResponse = $response->withStatus(302)->withHeader('Location', '/user/edit');
        return $newResponse;
    }

    public function resetURL($request, $response, $args)
    {
        $user = $this->user;
        $user->clean_link();
        $newResponse = $response->withStatus(302)->withHeader('Location', '/user');
        return $newResponse;
    }

    public function backtoadmin($request, $response, $args)
    {
        $userid = Utils\Cookie::get('uid');
        $adminid = Utils\Cookie::get('old_uid');
        $user = User::find($userid);
        $admin = User::find($adminid);

        if (!$admin->is_admin || !$user) {
            Utils\Cookie::set([
                "uid" => null,
                "email" => null,
                "key" => null,
                "ip" => null,
                "expire_in" => null,
                "old_uid" => null,
                "old_email" => null,
                "old_key" => null,
                "old_ip" => null,
                "old_expire_in" => null,
                "old_local" => null
            ], time() - 1000);
        }
        $expire_in = Utils\Cookie::get('old_expire_in');
        $local = Utils\Cookie::get('old_local');
        Utils\Cookie::set([
            "uid" => Utils\Cookie::get('old_uid'),
            "email" => Utils\Cookie::get('old_email'),
            "key" => Utils\Cookie::get('old_key'),
            "ip" => Utils\Cookie::get('old_expire_in'),
            "expire_in" => $expire_in,
            "old_uid" => null,
            "old_email" => null,
            "old_key" => null,
            "old_ip" => null,
            "old_expire_in" => null,
            "old_local" => null
        ], $expire_in);
        $newResponse = $response->withStatus(302)->withHeader('Location', $local);
        return $newResponse;
    }
}
