<?php
return [
    'icb'    => [
//        'url'            => 'https://onlinetest.funpay.co.kr/payment/', // test
'url'            => 'https://online.funpay.co.kr/payment/', // stable
'ver'            => '100',
'mid'            => 'P12000000175',
//        'secretKey'      => '749BD08F3766AB19AE5BC0B993CBABCD', // test
'secretKey'      => '749BD08F3766AB19AE5BC0B993CBD3B5', // stable
'wxpay_goods_id' => 4215,
'refer_url'      => 'https://pp.yunda.kr',
'return_url'     => 'https://pp.yunda.kr/mobile/#/pages/package/package',
'status_url'     => 'https://pp.yunda.kr/api/apis/payNotice',
    ],
    'wechat' => [
        'mch_id'     => '1511403471', // 微信支付分配的商户号
        'app_key'    => '888d43aa209b715128997ef3373ce96c',
        //微信商户平台(pay.weixin.qq.com)-->账户中心-->API安全-->密钥设置
        'app_id'     => 'wx495f699520babe6f', // 微信分配的小程序ID
        'app_secret' => '7925e884b7caf1530bc080de1fced004',
        'notify_url' => 'https://pp.yunda.kr/api/apis/payNoticeForLocalWechat',
        //异步回调url
    ],
];
