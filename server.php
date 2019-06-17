
<?php
define('CFG', include_once('config.php'));
// include_once('./json.php');
include_once('./sort.php');
class Poker {
    public $server;
    public $cfg;
    public $numpoker;
    public $rule;
    public $userlist = [];
    public $group = [['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]],['user'=>[]]];
    public function __construct() {
        $this->server = new swoole_websocket_server('0.0.0.0', 10005);
        // $this->server = new swoole_websocket_server('127.0.0.1', 10000);
        $this->cfg = CFG;
        $this->numpoker = CFG['card'];
        $this->rule = CFG['rule'];
        $this->server->on('open', function (swoole_websocket_server $server, $request) {
                $this->onOpen($server, $request);
            });
        $this->server->on('message', function (swoole_websocket_server $server, $frame){
            $this->onMessage($server, $frame);
        });
        $this->server->on('request', function ($request, $response) {
                $this->onRequest($request, $response);
            });
        $this->server->on('close', function ($ser, $fd) {
                $this->onClose($ser, $fd);
        });

        $this->server->start();
    }
    /*message数据结构
    *type:当前消息类型:
        *login 进入游戏
        *loginout 登出游戏
        *enter 进入房间
        *exit  退出房间
        *ready 准备游戏 数据结构:0未准备 1准备
        *putcard  出牌 数据结构:0不要，1出牌
        *call    叫地主0，抢地主1，不要2
        *start   开始游戏
        *end     游戏结束
    *data
    */
   /*$oepartion 
        0:'叫地主'
        1:'抢地主'
        2:'不叫'
        3:'准备'
        4:'取消'
        5:'退出房间'
        6:'不要'
        7:'出牌'
        8:'提示'
   */
    public function onMessage($server, $frame){
        //type::login,message,loginout,call,enter
        
        logs('单纯的没有进来');
        $data = json_decode($frame->data,true);
        //回复消息的数组message
        logs('收到数据'.$data);
        $m = [];
        $m['name'] = $data['name'];
        $m['type'] = $data['type'];
        $m['group'] = isset($data['group'])?$data['group']:"";
        $id = $this->getId($data['name']);
        logs('$id报错');
        switch($data['type']){
            case 'login':
                    $list = array_column($this->userlist, 'name');
                    if(in_array($data['name'], $list)){
                        foreach($this->userlist as &$v){
                            if($v['name'] == $data['name']){
                                if($v['login']){
                                    $m['message'] = '该帐号已经登录,请重新输入用户名';
                                    $m['type'] =  'error';
                                }else{
                                    $v['login'] = true;
                                    $v['id'] = $frame->fd;
                                    $m['message'] = $data['name']."上线了";
                                }
                            }
                        }
                    }else{
                        $user = [];
                        $fd = $frame->fd;
                        $user['login'] = true;
                        $m['message'] = $data['name']."上线了";
                        $user['name'] = $data['name'];
                        $user['ready'] = false;
                        $this->userlist[$fd] = $user;
                    }
                    $m['group'] = $this->group;
                    break;
            case 'loginout':break;
            case 'play':
                $card = $data['data'];
                //判断牌型是否正确
                $currgroup = &$this->group[$data['group']];
                if($iscard = $this->isTrueCard($card['data'])){
                    if(isset($currgroup['prep']) && $currgroup['prep'] != ""){
                        if($this->dataHandle($this->rule,$currgroup['prep'],$iscard)){
                            $this->delCard($frame->fd,$card['list'],$data['group']);
                            //牌出完了
                            if(count($this->userlist[$frame->fd]['card'])==0){
                                $m['type'] = "win";
                                $m['operation'] = [3,5];
                                $m['start'] = $currgroup['start'];
                                $m['currgroup'] = $currgroup;
                                $m["lander"] = $currgroup['lander'];
                                $this->resetGroup($data['group']);
                                $currgroup['operation'] = [3,5];
                                foreach ($currgroup['user'] as $v) {
                                    $this->userlist[$v]['ready'] = false;
                                }
                                
                                break;
                            }
                            $currgroup['prep'] = $iscard;
                            //确定出牌人，进行轮换
                            if($currgroup['start']==2){
                                $currgroup['start']=0;
                            }else{
                                $currgroup['start']++;
                            }

                            $currgroup['time']++;
                            $currgroup['call']=0;
                            $user = [];
                            array_push($user,$this->userlist[$currgroup['user'][0]]);
                            array_push($user,$this->userlist[$currgroup['user'][1]]);
                            array_push($user,$this->userlist[$currgroup['user'][2]]);
                            $m['msg'] = '牌型正确';
                            $m['status'] = '1001';
                            $m['currgroup'] = $currgroup;
                            $m['operation'] = [6,7,8];
                            var_dump($m);

                            break;
                        }
                    }else{
                        //确定出牌人，进行轮换
                        
                        $this->delCard($frame->fd,$card['list'],$data['group']);
                        if(count($this->userlist[$frame->fd]['card'])==0){
                            $m['type'] = "win";
                            $m['operation'] = [3,5];
                            $m['start'] = $currgroup['start'];
                            $m['currgroup'] = $currgroup;
                            $m["lander"] = $currgroup['lander'];
                            $this->resetGroup($data['group']);
                            $currgroup['operation'] = [3,5];
                            foreach ($currgroup['user'] as $v) {
                                $this->userlist[$v]['ready'] = false;
                            }
                            break;
                        }
                        if($currgroup['start']==2){
                            $currgroup['start']=0;
                        }else{
                            $currgroup['start']++;
                        }
                        $currgroup['call']=0;
                        $currgroup['time'] = 0;
                        $user = [];                        
                        $currgroup['prep'] = $iscard;
                        array_push($user,$this->userlist[$currgroup['user'][0]]);
                        array_push($user,$this->userlist[$currgroup['user'][1]]);
                        array_push($user,$this->userlist[$currgroup['user'][2]]);
                        $m['msg'] = '牌型正确';
                        $m['status'] = '1001';
                        $m['currgroup'] = $currgroup;
                        $m['operation'] = [6,7,8];
                        break;
                    }
                }
                $m['type'] == 'error';
                $m['message'] = '您出的牌不正确';
                break;
            case 'enter':
                    
                    $m['operation']=[3,5];
                    foreach($this->group as $v){
                        if(in_array($frame->fd,$v)){
                            $m['message'] = '你已经加入其他房间,请勿多次加入';
                            $m['type'] = 'error';
                        }
                    }
                    logs('enter房间');
                    
                    $currgroup = $this->group[$data['group']]['user'];
                    $this->userlist[$frame->fd]['group'] = $data['group'];
                    
                    logs('数据'.json_encode($data));
                    logs('所有数据'.json_encode($currgroup));
                    logs('用户列表'.json_encode($this->userlist));
                    if(count($currgroup) < 3){
                        logs('正常执行所有操作,进入房间');
                        array_push($this->group[$data['group']]['user'],$frame->fd);
                        $m['group'] = $data['group']; $m['message'] = $data['name']."进入房间";
                    }else{
                        logs('正常执行所有操作,满员');
                        $m['message'] = '该房间已经满员,请重新选择房间';
                        $m['type'] = 'error';
                    }
                    break;
            case 'exit':
                $currgroup = &$this->group[$data['group']]['user'];
                $offset = array_search($frame->fd,$currgroup);
                array_splice($currgroup,$offset,1);
                $m['message'] = $data['name']."退出房間成功";
                break;
            case 'ready':
                    //
                    $this->userlist[$id]['ready'] = !$this->userlist[$id]['ready'];
                    if($this->userlist[$id]['ready']){
                        $m['operation']=[4];
                    }else{
                        $m['operation']=[3,5];
                    }
                    $start = true;
                    $groupId = $data['group'];
                    $currgroup = $this->group[$groupId];
                    $groupUser =  $currgroup['user'];
                    foreach($groupUser  as $k => $v){
                        if($this->userlist[$v]['ready'] == 0){
                            $start = false;
                        }
                    }
                    if(count($groupUser) == 3 && $start){
                        $m['type'] = 'putcard';
                        $m['card'] = $this->randCard($groupId);
                        $m['group'] = $groupId;
                        $m['operation'] = [0,2];
                        //初始化当前牌局
                        $this->group[$groupId]['card'] = $m['card'];
                        $this->group[$groupId]['start'] = rand(0,2);
                        $this->group[$groupId]['lander'] = $this->group[$groupId]['start'];

                        $this->group[$groupId]['call'] = 0;
                        $this->group[$groupId]['nocall'] = 0;
                        $m['currgroup'] = $this->group[$groupId];
                    }

                    break;
            case 'start':break;
            case 'end':break;
            case "giveup":
                    //确定出牌人，进行轮换
                    $currgroup = &$this->group[$data['group']];

                    echo "我是".$currgroup['start']."个".$currgroup['start'];
                    if($currgroup['start']==2){
                        $currgroup['start']=0;
                    }else{
                        $currgroup['start']++;
                    }
                    $currgroup['call']++;
                    if($currgroup['call'] == 2 ){
                        $currgroup['prep']="";
                        $m['operation'] = [7,8];
                    }else{
                        $m['operation'] = [6,7,8];
                    }
                    $m['currgroup'] = $currgroup;

                    break;
            case 'call':

                $currgroup = &$this->group[$data['group']];

                var_dump($currgroup);
                $currgroup['call']++;
                
                if($data['opera']!=2){
                    $currgroup['lander'] = $data['value'];
                }else{
                    $currgroup['nocall']++;
                }
                if($currgroup['call'] <= 3 && !($currgroup['nocall'] == 2 &&  $currgroup['call'] == 3) ){
                    //判断是否为不叫

                    $m['operation'] = [1,2];
                    if($currgroup['start']==2){
                        $currgroup['start']=0;
                    }else{
                        $currgroup['start']++;
                    }
                }else{
                    
                    $currgroup['start'] = $currgroup['lander'];
                    //三轮地主抢完，确认地主人选
                    $m['type'] = 'start';
                    echo $data['opera'];
                    echo $currgroup['nocall'];
                    $m['nocall'] = $currgroup['nocall'];
                    //出牌和提示
                    $m['operation'] = [7,8];
                    $arr = array_merge($currgroup['card'][0][$currgroup['lander']]['card'],$currgroup['card'][1]);
                    if($this->group[$data['group']]['user'][$currgroup['lander']] == $frame->fd){
                        $this->userlist[$frame->fd]['card'] = $arr;
                    }
                    $this->group[$data['group']]['card'][0][$currgroup['lander']]['card'] = $arr;
                    
                }
                $m['opera'] = $data['opera'];
                $m['currgroup'] = $this->group[$data['group']];
                break;

        }
        $groupid = isset($data['group'])?$data['group']:false;
        logs('最后操作除了问题'.$groupid);
        logs('记录fd'.$frame->fd);
        $this->sendMessage($m,$groupid);
    }
    public function onOpen($server, $request){
    //  $user = [];
    //  if(count($this->userlist)==0){
    //      // $user['name'] = 'user'.$request->fd;
    //      $user['id'] = $request->fd;
    //      $user['login'] = true;
    //      array_push($this->userlist,$user);
    //  }
    //  $status=1;
    }
    public function onRequest($request, $response){
        // 接收http请求从get获取message参数的值，给用户推送
        // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
        foreach ($this->server->connections as $fd) {
            $this->server->push($fd, $request->get['message']);
        }
    }
    public function onClose($server,$fd){
        if(!isset($this->userlist[$fd]['group'])){
            return;
        }
        $group = $this->userlist[$fd]['group'];
        echo $group;
        $currgroup = &$this->group[$group];
        $offset = array_search($fd,$currgroup['user']);
        array_splice($currgroup['user'],$offset,1);


        $m['type'] = "gameover";
        $m['message'] = $this->userlist[$fd]['name']."退出了房间";
        $m['operation'] = [3,5];
        $this->resetGroup($group);
        $currgroup['operation'] = [3,5];
        foreach ($currgroup['user'] as $v) {
            $this->userlist[$v]['ready'] = false;
        }
        $this->sendMessage($m,$group);
    }
   
    public function sendMessage($m,$id){
        $message = json_encode($m);
        
        logs('问题'.$message);
        if(gettype($id) != "integer"){
            
            logs('路线:world'.implode(",",$this->server->connections));
            foreach ($this->server->connections as $fd) {
                logs('最后一步:'.$fd.'----------------'.$messagbe);
                $this->server->push($fd,$message);
            }
        }else{
            logs('路线:enterRoom,所有连接人'.implode(",",$this->server->connections));
            logs('all数据'.json_encode($this->group[$id]));
            logs('alluser'.json_encode($this->group[$id]['user']));
            foreach ($this->server->connections as $fd) {
                if(gettype(array_search($fd,$this->group[$id]['user'])) == "integer"){
                    logs('最后一步:'.$fd.'----------------'.$messagbe);
                    $this->server->push($fd,$message);
                }
            }
        }
        
        
    }
    /*大小对比***********
    *prep:上一手牌
    *next:当前出牌
    *数据格式
    *   [
    *       'data'=>[['type'=>'space','value'=>'5','level'=>'3']],
    *       'len' => 1,
    *       'type' = 'single'
    *   ]
    */
    public function dataHandle($rule,$prep,$curr){
        $bool = false;
        if($prep['type'] == $curr['type']){
            if($prep['data'][0]['level'] < $curr['data'][0]['level']){
                $bool = true;
            }
        }else if($rule[$prep['type']]['level'] < $rule[$curr['type']]['level'])
        {   


            $bool = true;
        }
        return $bool;
    }


    /*删除已出的牌***********
    *n:组
    *id,用户id
    *data:data
    */
    public function delCard($id,$data,$group){
        $currgroup = &$this->group[$group];
        $card = &$this->userlist[$id]['card'];
        $arr = &$currgroup['card'][0][$currgroup['start']]['card'];
        foreach($data as $v){
            $offset = array_search($v,$card);
            array_splice($card,$offset,1);            
            $ofset = array_search($v,$arr);
            array_splice($arr,$ofset,1);
        }
    }
    public function resetGroup($id){
        $currgroup = &$this->group[$id];
        unset($currgroup['card']);
        unset($currgroup['start']);
        unset($currgroup['lander']);
        unset($currgroup['call']);
        unset($currgroup['prep']);
    }
    //牌型去重
    public function repeat($data){
        $list = [];
        $arr = [];
        foreach($data as $item){
            $key = array_search($item['level'],$arr);
            if(gettype($key)!="integer"){
                $obj = [];
                $obj['level']=$item['level'];
                $obj['num']=1;              
                array_push($arr,$item['level']);
                array_push($list,$obj);
            }else{
                $list[$key]['num']++;
            }
        }
        return $list;
    }
    //牌型确认
    public function isTrueCard ($data){
        //从小到大排序,数组反转
        logs('这牌'.json_encode($data));
        $list = $data;
        $arr = array_sort($this->repeat($list),'num',SORT_DESC);
        $current =[];
        $isKing = [20,30];        
        $current['data'] = $list;
        if(count($arr)==0){
            return false;
        }
        logs('当前牌型,'.json_encode($arr));
        switch($arr[0]['num']){
            case 1:
                if(count($list) == 1){
                    $current['type'] = 'one';
                    $current['level'] = $arr[0]['level'];
                    $current['other'] = '';
                    break;
                }else if(gettype(array_search($list[0]['level'],$isKing)) =="integer"  && gettype(array_search($list[0]['level'],$isKing)) == "integer" &&  count($list) == 2){
                    $current['type'] = 'kingtwo';
                    break;
                }else if(count($list) > 4 ){
                    $sarr = array_sort($arr,'level',SORT_DESC);
                    $drr = $this->cardfilter($sarr);
                    if(count($drr) == count($arr)){
                        $current['type'] = 'list';
                        $current['len']= count($sarr);
                        break;
                    }
                }
                // console.log($current);
                return false;
                break;
            case 2:
                if(count($arr)== 1){
                    $current['type'] = 'two';
                    $current['level'] = $arr[0]['level'];
                    break;
                }else if(count($arr) >2 ){
                    //以arr降序排列，进行顺子筛选和单牌数量筛选
                    $sarr =  array_filter($this->cardfilter(array_sort($arr,'level',SORT_DESC), function($v, $k) {
                            return $v['num']==2;
                        }, ARRAY_FILTER_USE_BOTH));
                    if(count($sarr) == count($arr)){
                        $current['type'] = 'twomore';
                        $current['len'] = count($arr);
                        break;
                    }
                }
                return false;
                break;
            case 3:
                if(count($arr) == 1){
                    $current['type'] = 'three';
                    $current['level'] = $list[0]['level'];
                    break;
                }else   if(count($arr) >1){
                        $threeArr = array_filter($arr, function($v, $k) {
                            return $v['num']==3;
                        }, ARRAY_FILTER_USE_BOTH);

                        $val = 0;
                        foreach($arr as $k=>$v){
                            $val += $v['num'];
                        }
                        $onenum = $val-count($threeArr)*3;
                        if($onenum==0 && count($threeArr) > 1){
                            $current['type'] = 'threemore';
                            $current['len'] = count($threeArr);
                            break;
                        }else if($onenum == 1 && count($threeArr) == 1){
                            $current['type'] = 'three';
                            $current['level'] = $arr[0]['level'];
                            $current['other'] = 1;
                            break;
                        }else if($onenum == count($threeArr)){
                            $darr = $this->cardfilter(array_sort($threeArr,'level',SORT_DESC));
                            if(count($darr) == count($threeArr)){
                                $current['type'] = 'threemore';
                                $current['len'] = count($threeArr);
                                break;
                            }
                        }
                }
                return false;
                break;
            case 4:
                if(count($arr) == 1){
                    $current['type'] = 'four';
                    $current['level'] = $list[0]['level'];
                    break;
                }else if(count($arr)>1){
                    $fourArr = array_filter($arr, function($v, $k) {
                            return $v['num']==4;
                        }, ARRAY_FILTER_USE_BOTH);
                    $val = 0;
                    foreach($arr as $k=>$v){
                        $val += $v['num'];
                    }
                    $onenum = $val-count($fourArr)*4;;
                    if($onenum==0 && count($fourArr) > 1){
                        $current['type'] = 'fourmore';
                        break;
                    }else if($onenum == count($fourArr) || $onenum/count($fourArr)==2){
                        $darr = $this->cardfilter(array_sort($fourArr,'level',SORT_DESC));
                        if(count($darr) == count($fourArr)){
                            $current['type'] = 'fourmore';
                            $current['len'] = count($fourArr);
                            break;
                        }
                    }else {
                        $fournum = array_filter($arr, function($v, $k) {
                            return $v['num']==4;
                        }, ARRAY_FILTER_USE_BOTH);
                        $threenum = array_filter($arr, function($v, $k) {
                            return $v['num']==3;
                        }, ARRAY_FILTER_USE_BOTH); 
                        $othernum = array_filter($arr, function($v, $k) {
                            return $v['num'] !=3 && $v['num'] != 4;
                        }, ARRAY_FILTER_USE_BOTH);
                        if(count($threenum) == count($othernum)){
                            $current['type'] = 'threemore';
                            $current['len'] = count($threenum)+count($fournum);
                            break;
                        }
                    }
                }   
                return false;
                break;
        }
        return $current;

    }
    //得到当前人物的数据
    public function getId ($name){
        foreach ($this->userlist as $k => $v){
            if($v['name'] == $name){
                return $k;
            }
        }
    }
    public function cardfilter($data){
        $list = [];
        array_push($list,$data[0]);
        for($i=0;$i<count($data)-1;$i++){
            if($data[$i]['level']-$data[$i+1]['level']==1){
                array_push($list,$data[$i+1]);
            }else{
                return $list;
            }
        }
        return $list;
    }
    /*发牌，初始化组数据*********** 
    *n:组ID
    */
    public function randCard($group_number){
        $arr = range(0,53);
        $list = shuffle($arr);
        $currgroup = $this->group[$group_number]['user'];
        $this->userlist[$currgroup[0]]['card']=[];
        $this->userlist[$currgroup[1]]['card']=[];
        $this->userlist[$currgroup[2]]['card']=[];
        $endhand = [];
        for($i=0;$i<count($arr);$i++){
            if($i>50){
                array_push($endhand,$arr[$i]);
                continue;
            }
            $k = floor(($i)/17);
            array_push($this->userlist[$currgroup[$k]]['card'],$arr[$i]);
        }
        $user = [];
        $card = [];
        array_push($user,$this->userlist[$currgroup[0]]);
        array_push($user,$this->userlist[$currgroup[1]]);
        array_push($user,$this->userlist[$currgroup[2]]);
        array_push($card,$user);
        array_push($card,$endhand);
        var_dump($card);
        return $card;


    }
}
new Poker();
?>