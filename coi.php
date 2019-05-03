<?php
$oslist = array('win', 'mac');
$channellist = array('stable', 'beta', 'dev', 'canary');
$archlist = array('x64', 'x86');

function getchromelinks ($os, $channel, $arch) {
    $verlist = array(
        'win' => '6.3',
        'mac' => '46.0.2490.86'
    );
    $appidlist = array(
        'win_stable' => '{8A69D345-D564-463C-AFF1-A69D9E530F96}',
        'win_beta' => '{8A69D345-D564-463C-AFF1-A69D9E530F96}',
        'win_dev' => '{8A69D345-D564-463C-AFF1-A69D9E530F96}',
        'win_canary' => '{4EA16AC7-FD5A-47C3-875B-DBF4A2008C20}',
        'mac_stable' => 'com.google.Chrome',
        'mac_beta' => 'com.google.Chrome',
        'mac_dev' => 'com.google.Chrome',
        'mac_canary' => 'com.google.Chrome.Canary'
    );
    $aplist = array(
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
    
    $ver = $verlist[$os];
    $appid = $appidlist[$os.'_'.$channel];
    $ap = $aplist[$os.'_'.$channel.'_'.$arch];

    $postData = <<<postdata
<?xml version='1.0' encoding='UTF-8'?>
<request protocol='3.0' version='1.3.23.9' shell_version='1.3.21.103' ismachine='0'
    sessionid='{3597644B-2952-4F92-AE55-D315F45F80A5}' installsource='ondemandcheckforupdate'
    requestid='{CD7523AD-A40D-49F4-AEEF-8C114B804658}' dedup='cr'>
<hw sse='1' sse2='1' sse3='1' ssse3='1' sse41='1' sse42='1' avx='1' physmemory='12582912' />
<os platform='{$os}' version='{$ver}' arch='{$arch}'/>
<app appid='{$appid}' ap='{$ap}' version='' nextversion='' lang='' brand='GGLS' client=''><updatecheck/></app>
</request>
postdata;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://tools.google.com/service/update2');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $xml = curl_exec($ch);
    curl_close($ch);
    return $xml;
}

$apijson = array();
foreach ($oslist as $os) {
    foreach ($channellist as $channel) {
        foreach ($archlist as $arch) {

            # x64 only
            if ($os == 'mac' && $arch == 'x86') {
                continue;
            }

            $output = getchromelinks($os, $channel, $arch);
            
            # XML to Array
            $output = json_decode(json_encode(simplexml_load_string($output)), TRUE);

            $urls = array();
            foreach ($output['app']['updatecheck']['urls']['url'] as $url) {
                $url = $url['@attributes']['codebase'].$output['app']['updatecheck']['manifest']['packages']['package']['@attributes']['name'];
                array_push($urls, $url);
            }

            $apijson[$os.'_'.$channel.'_'.$arch] = array(                    
                "version" => $output['app']['updatecheck']['manifest']['@attributes']['version'],
                "size" => $output['app']['updatecheck']['manifest']['packages']['package']['@attributes']['size'],
                "sha256" => $output['app']['updatecheck']['manifest']['packages']['package']['@attributes']['hash_sha256'],
                "urls" => $urls
            );
        }
    }
}

echo json_encode($apijson);
?>