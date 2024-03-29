<?php
//
//include ("ZopClient.php");
//include ("ZopProperties.php");
//include ("ZopRequest.php");


require "ZopClient.php";
require "ZopProperties.php";
require "ZopRequest.php";

use zop\ZopClient;
use zop\ZopProperties;
use zop\ZopRequest;


$properties = new ZopProperties("kfpttestCode", "kfpttestkey==");
$client     = new ZopClient($properties);
$request    = new ZopRequest();
$request->setUrl("https://japi-test.zto.com/submitOrderCode");
$request->setBody(
    '{"data":{"content":{"branchId":"","buyer":"","collectMoneytype":"CNY","collectSum":"12.00","freight":"10.00","id":"xfs2018031500002222333","orderSum":"0.00","orderType":"1","otherCharges":"0.00","packCharges":"1.00","premium":"0.50","price":"126.50","quantity":"2","receiver":{"address":"育德路XXX号","area":"501022","city":"四川省,XXX,XXXX","company":"XXXX有限公司","email":"yyj@abc.com","id":"130520142097","im":"yangyijia-abc","mobile":"136*****321","name":"XXX","phone":"010-222***89","zipCode":"610012"},"remark":"请勿摔货","seller":"","sender":{"address":"华新镇华志路XXX号","area":"310118","city":"上海,上海市,青浦区","company":"XXXXX有限公司","email":"ll@abc.com","endTime":1369033200000,"id":"131*****010","im":"1924656234","mobile":"1391***5678","name":"XXX","phone":"021-87***321","startTime":1369022400000,"zipCode":"610012"},"size":"12,23,11","tradeId":"2701843","type":"1","typeId":"","weight":"0.753"},"datetime":"2018-3-30 12:00:00","partner":"test","verify":"ZTO123"}}'
);
echo $client->execute($request);

?>