<?php

function getPosts() {
    $platforms = array(
		'win' => array('x64', 'x86'),
		'mac' => array('x64')
	);
    $channels = array('stable', 'beta', 'dev', 'canary');
    $vers = array(
        'win' => '6.3',
        'mac' => '46.0.2490.86'
    );
    $appids = array(
        'win_stable' => '{8A69D345-D564-463C-AFF1-A69D9E530F96}',
        'win_beta' => '{8A69D345-D564-463C-AFF1-A69D9E530F96}',
        'win_dev' => '{8A69D345-D564-463C-AFF1-A69D9E530F96}',
        'win_canary' => '{4EA16AC7-FD5A-47C3-875B-DBF4A2008C20}',
        'mac_stable' => 'com.google.Chrome',
        'mac_beta' => 'com.google.Chrome.Beta',
        'mac_dev' => 'com.google.Chrome.Dev',
        'mac_canary' => 'com.google.Chrome.Canary'
    );
    $aps = array(
        'win_stable_x86' => '-multi-chrome',
        'win_stable_x64' => 'x64-stable-multi-chrome',
        'win_beta_x86' => '1.1-beta',
        'win_beta_x64' => 'x64-beta-multi-chrome',
        'win_dev_x86' => '2.0-dev',
        'win_dev_x64' => 'x64-dev-multi-chrome',
        'win_canary_x86' => '',
        'win_canary_x64' => 'x64-canary',
        'mac_stable_x86' => '',
        'mac_stable_x64' => '',
        'mac_beta_x86'=>'betachannel',
        'mac_beta_x64' => 'betachannel',
        'mac_dev_x86' => 'devchannel',
        'mac_dev_x64' => 'devchannel',
        'mac_canary_x86' => '',
        'mac_canary_x64' => ''
    );

    $posts = array();
    foreach ($platforms as $os => $arches) {
        foreach ($channels as $channel) {
            foreach ($arches as $arch) {
                $ver = $vers[$os];
                $appid = $appids[$os.'_'.$channel];
                $ap = $aps[$os.'_'.$channel.'_'.$arch];

                $posts[$os.'_'.$channel.'_'.$arch] = <<<postdata
<?xml version='1.0' encoding='UTF-8'?>
<request protocol='3.0' version='1.3.23.9' shell_version='1.3.21.103' ismachine='0'
    sessionid='{3597644B-2952-4F92-AE55-D315F45F80A5}' installsource='ondemandcheckforupdate'
    requestid='{CD7523AD-A40D-49F4-AEEF-8C114B804658}' dedup='cr'>
<hw sse='1' sse2='1' sse3='1' ssse3='1' sse41='1' sse42='1' avx='1' physmemory='12582912' />
<os platform='{$os}' version='{$ver}' arch='{$arch}'/>
<app appid='{$appid}' ap='{$ap}' version='' nextversion='' lang='' brand='GGLS' client=''><updatecheck/></app>
</request>
postdata;
            }
        }
    }
    return $posts;
}

// Multi Requests
$posts = getPosts();
$res = array();
$ch = array();
$mh = curl_multi_init();
foreach ($posts as $key => $postData) {
    $ch[$key] = curl_init();
    curl_setopt($ch[$key], CURLOPT_URL, 'https://tools.google.com/service/update2');
    curl_setopt($ch[$key], CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch[$key], CURLOPT_POST, 1);
    curl_setopt($ch[$key], CURLOPT_POSTFIELDS, $postData);
    curl_multi_add_handle($mh, $ch[$key]);
}
$active = null;
do {
    $mrc = curl_multi_exec($mh, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);
while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($mh) != -1) {
        do {
            $mrc = curl_multi_exec($mh, $active);

        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
}
foreach ($posts as $key => $postData) {
    $info = curl_multi_info_read($mh);
    $heards = curl_getinfo($ch[$key]);
    $res[$key]['error'] = curl_error($ch[$key]);
    $res[$key]['content'] = curl_multi_getcontent($ch[$key]);
    curl_multi_remove_handle($mh, $ch[$key]);
    curl_close($ch[$key]);
}
curl_multi_close($mh);

// XML to Array
foreach ($res as $key => $value) {
    $xml = json_decode(json_encode(simplexml_load_string($value['content'])), TRUE);
    if ($value['error']) {
        $res[$key] = array(
            "error" => $value['error'],
            "version" => "",
            "size" => 0,
            "sha256" => "",
            "urls" => array()
        );
    } elseif (!$xml) {
        $res[$key] = array(
            "error" => "Failed to parse XML text",
            "version" => "",
            "size" => 0,
            "sha256" => "",
            "urls" => array()
        );
    } else {
        $urls = array();
        foreach ($xml['app']['updatecheck']['urls']['url'] as $url) {
            $url = $url['@attributes']['codebase'].$xml['app']['updatecheck']['manifest']['packages']['package']['@attributes']['name'];
            array_push($urls, $url);
        }

        $res[$key] = array(
            "error" => "",
            "version" => $xml['app']['updatecheck']['manifest']['@attributes']['version'],
            "size" => $xml['app']['updatecheck']['manifest']['packages']['package']['@attributes']['size'],
            "sha256" => $xml['app']['updatecheck']['manifest']['packages']['package']['@attributes']['hash_sha256'],
            "urls" => $urls
        );
    }
}

header('Content-type: application/json; charset=utf-8');
echo json_encode($res);
?>
