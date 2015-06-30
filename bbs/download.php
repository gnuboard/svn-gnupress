<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

//wp-super-cache 사용시 이 페이지는 cache하지 않음
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
    define( 'DONOTCACHEPAGE', true );
}

ob_end_clean();

$no = isset($_REQUEST['no']) ? (int) $_REQUEST['no'] : 0;

@include_once($board_skin_path.'/download.head.skin.php');

// 쿠키에 저장된 ID값과 넘어온 ID값을 비교하여 같지 않을 경우 오류 발생
// 다른곳에서 링크 거는것을 방지하기 위한 코드
if (!g5_get_session('ss_view_'.$bo_table.'_'.$wr_id))
    g5_alert('잘못된 접근입니다.');

// 다운로드 차감일 때 비회원은 다운로드 불가

if($board['bo_download_point'] < 0 && $is_guest)
    g5_alert('다운로드 권한이 없습니다.\\n회원이시라면 로그인 후 이용해 보십시오.', wp_login_url( add_query_arg( array_merge( (array) $qstr, array('wr_id'=>$wr_id)), $default_href ) ) );

$file_meta_data = get_metadata(G5_META_TYPE, $wr_id, G5_FILE_META_KEY , true );
if( !isset($file_meta_data[$no]['bf_file']) || empty($file_meta_data[$no]['bf_file']) )
   g5_alert_close('파일 정보가 존재하지 않습니다.');

$file = $file_meta_data[$no];

// JavaScript 불가일 때
/*
if($js != 'on' && $board['bo_download_point'] < 0) {
    $msg = $file['bf_source'].' 파일을 다운로드 하시면 포인트가 차감('.number_format($board['bo_download_point']).'점)됩니다.\\n포인트는 게시물당 한번만 차감되며 다음에 다시 다운로드 하셔도 중복하여 차감하지 않습니다.\\n그래도 다운로드 하시겠습니까?';
    $url1 = add_query_arg( array('js'=>'on') );
    $url2 = wp_get_referer();

    //$url1 = 확인link, $url2=취소link
    // 특정주소로 이동시키려면 $url3 이용
    g5_confirm($msg, $url1, $url2);
}
*/

if ($member['user_level'] < $board['bo_download_level']) {
    $alert_msg = '다운로드 권한이 없습니다.';
    if ($member['user_id']){
        g5_alert($alert_msg);
    } else {
        g5_alert($alert_msg.'\\n회원이시라면 로그인 후 이용해 보십시오.');
    }
}

if( !g5_get_upload_path() ) 
    g5_alert('파일이 존재하지 않습니다.');

$filepath = g5_get_upload_path().'/file/'.$bo_table.'/'.$file['bf_file'];
$filepath = addslashes($filepath);
if (!is_file($filepath) || !file_exists($filepath))
    g5_alert('파일이 존재하지 않습니다.');

// 사용자 코드 실행
@include_once($board_skin_path.'/download.skin.php');

// 이미 다운로드 받은 파일인지를 검사한 후 게시물당 한번만 포인트를 차감하도록 수정
$ss_name = 'ss_down_'.$bo_table.'_'.$wr_id;

if (g5_get_session($ss_name))
{
    // 자신의 글이라면 통과
    // 관리자인 경우 통과
    if (($write['user_id'] && $write['user_id'] == $member['user_id']) || $is_admin)
        ;
    else if ($board['bo_download_level'] >= 0) // 회원이상 다운로드가 가능하다면
    {
        // 다운로드 포인트가 음수이고 회원의 포인트가 0 이거나 작다면
        if ($member['mb_point'] + $board['bo_download_point'] < 0)
            g5_alert('보유하신 포인트('.number_format($member['mb_point']).')가 없거나 모자라서 다운로드('.number_format($board['bo_download_point']).')가 불가합니다.\\n\\n포인트를 적립하신 후 다시 다운로드 해 주십시오.');

        // 게시물당 한번만 차감하도록 수정
        g5_insert_point($member['user_id'], $board['bo_download_point'], "{$board['bo_subject']} $wr_id 파일 다운로드", $bo_table, $wr_id, "다운로드");
    }

    // 다운로드 카운트 증가
    $file_meta_data[$no]['bf_download'] = (int) $file_meta_data[$no]['bf_download'] + 1;
    update_metadata( G5_META_TYPE, $wr_id, G5_FILE_META_KEY, $file_meta_data );
    g5_set_session($ss_name, TRUE);
}

$g5['title'] = '다운로드 &gt; '.g5_conv_subject($write['wr_subject'], 255);
$original = urlencode($file['bf_source']);

@include_once($board_skin_path.'/download.tail.skin.php');

if(preg_match("/msie/i", $_SERVER['HTTP_USER_AGENT']) && preg_match("/5\.5/", $_SERVER['HTTP_USER_AGENT'])) {
    header("content-type: doesn/matter");
    header("content-length: ".filesize("$filepath"));
    header("content-disposition: attachment; filename=\"$original\"");
    header("content-transfer-encoding: binary");
} else {
    header("content-type: file/unknown");
    header("content-length: ".filesize("$filepath"));
    header("content-disposition: attachment; filename=\"$original\"");
    header("content-description: php generated data");
}
header("pragma: no-cache");
header("expires: 0");
flush();


$fp = fopen($filepath, 'rb');

// 4.00 대체
// 서버부하를 줄이려면 print 나 echo 또는 while 문을 이용한 방법보다는 이방법이...
//if (!fpassthru($fp)) {
//    fclose($fp);
//}

$download_rate = 10;

while(!feof($fp)) {
    //echo fread($fp, 100*1024);
    /*
    echo fread($fp, 100*1024);
    flush();
    */

    print fread($fp, round($download_rate * 1024));
    flush();
    usleep(1000);
}
fclose ($fp);
flush();


exit;
?>