<?php
/**
 * Plugin Name: 安城ナビ 店舗管理
 * Description: SWELL対応の店舗管理・検索・既存データ取り込み。
 * Version: 1.0.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) { exit; }
function anjod_fields() {
    return array('address'=>'住所','phone'=>'電話番号','keywords'=>'検索キーワード','status'=>'確認状況（例：公式サイト確認／営業状況要確認）','checkedAt'=>'情報の確認日（YYYY-MM-DD）','reviewedAt'=>'調査日（未確認の場合）','source'=>'情報源URL','sourceLabel'=>'情報源の名称','note'=>'利用者向けの補足','verificationType'=>'確認種別（管理用）');
}
function anjod_init() {
    register_post_type('anjod_shop',array('labels'=>array('name'=>'店舗情報','singular_name'=>'店舗情報','add_new'=>'店舗を追加','add_new_item'=>'店舗を追加','edit_item'=>'店舗情報を編集','search_items'=>'店舗を検索','all_items'=>'店舗一覧'),'public'=>true,'show_in_rest'=>false,'menu_icon'=>'dashicons-store','supports'=>array('title','editor','revisions'),'rewrite'=>array('slug'=>'anjo-shop'),'has_archive'=>false));
    register_taxonomy('anjod_category','anjod_shop',array('label'=>'業種','public'=>true,'hierarchical'=>true,'show_admin_column'=>true,'rewrite'=>false));
    register_taxonomy('anjod_tag','anjod_shop',array('label'=>'特徴','public'=>true,'hierarchical'=>false,'show_admin_column'=>true,'show_ui'=>true,'rewrite'=>false));
    register_post_type('anjod_fixreq',array('labels'=>array('name'=>'修正依頼','singular_name'=>'修正依頼','all_items'=>'修正依頼一覧'),'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=anjod_shop','show_in_rest'=>false,'menu_icon'=>'dashicons-email-alt','supports'=>array('title'),'capabilities'=>array('create_posts'=>'do_not_allow'),'map_meta_cap'=>true));
}
add_action('init','anjod_init');
function anjod_seed_tags(){
    foreach(array('駐車場あり','テイクアウト可','子ども連れOK','ペットOK','Wi-Fiあり','キャッシュレス可','個室あり') as $t){
        if(!term_exists($t,'anjod_tag'))wp_insert_term($t,'anjod_tag');
    }
}
add_action('init',function(){anjod_seed_tags();},20);
register_activation_hook(__FILE__,function(){anjod_init();anjod_seed_tags();flush_rewrite_rules();});
register_deactivation_hook(__FILE__,function(){flush_rewrite_rules();});
add_action('add_meta_boxes',function(){add_meta_box('anjod_details','店舗の基本情報','anjod_metabox','anjod_shop','normal','high');});
function anjod_metabox($post) {
    wp_nonce_field('anjod_save','anjod_nonce');
    echo '<p>店名は上のタイトル欄、業種は右の「業種」で変更します。確認日は実際に情報を確認した日を入力してください。</p><table class="form-table">';
    foreach(anjod_fields() as $key=>$label){
        $value=get_post_meta($post->ID,'_anjod_'.$key,true);
        echo '<tr><th><label for="anjod_'.esc_attr($key).'">'.esc_html($label).'</label></th><td>';
        if($key==='note'){echo '<textarea class="large-text" rows="3" id="anjod_'.esc_attr($key).'" name="anjod['.esc_attr($key).']">'.esc_textarea($value).'</textarea>';}
        else{echo '<input class="widefat" type="'.($key==='source'?'url':'text').'" id="anjod_'.esc_attr($key).'" name="anjod['.esc_attr($key).']" value="'.esc_attr($value).'">';}
        echo '</td></tr>';
    }
    echo '</table>';
}
add_action('save_post_anjod_shop',function($id){
    if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;
    if(wp_is_post_revision($id)||!current_user_can('edit_post',$id))return;
    if(!isset($_POST['anjod_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['anjod_nonce'])),'anjod_save'))return;
    $values=isset($_POST['anjod'])&&is_array($_POST['anjod'])?wp_unslash($_POST['anjod']):array();
    foreach(anjod_fields() as $key=>$label){
        $v=isset($values[$key])&&is_scalar($values[$key])?(string)$values[$key]:'';
        $v=$key==='source'?esc_url_raw($v):($key==='note'?sanitize_textarea_field($v):sanitize_text_field($v));
        update_post_meta($id,'_anjod_'.$key,$v);
    }
});
add_action('admin_menu',function(){add_submenu_page('edit.php?post_type=anjod_shop','データ取り込み','データ取り込み','manage_options','anjod-import','anjod_import_page');});
function anjod_import_page(){
    if(!current_user_can('manage_options'))return;
    $offset=(int)get_option('anjod_import_offset',0);
    $srcOffset=(int)get_option('anjod_fix_source_offset',0);
    $floorOffset=(int)get_option('anjod_fix_floor_offset',0);
    $phoneOffset=(int)get_option('anjod_fix_phone_offset',0);
    echo '<div class="wrap"><h1>安城ナビ：データ取り込み</h1><p>同梱データを50件ずつ取り込みます。画面を閉じても再開できます。同じデータを二重登録せず、取り込み済み店舗の編集内容を上書きしません。</p><p>確認日と確認状況は元データのまま引き継ぎます。取り込みは営業状況の再検証ではありません。</p><button class="button button-primary" id="anjod-run">取り込みを開始・再開</button><p role="status" id="anjod-progress">処理済み：'.esc_html($offset).'件</p><hr><h2>検索ページを作成</h2><p>取り込み完了後に実行してください。固定ページ「安城ナビ」を公開します。トップページへの設定は「設定 → 表示設定」で選べます。</p><button class="button" id="anjod-page">検索ページを作成</button><p id="anjod-page-result"></p><hr><h2>情報修正フォームのページを作成</h2><p>店舗オーナー向けに、電話番号・営業時間などの修正を依頼できるフォームページ「情報修正はこちら」を公開します。送信内容は自動反映されず、「修正依頼」一覧で確認してから手動で反映してください。</p><button class="button" id="anjod-fixreq-page">修正フォームページを作成</button><p id="anjod-fixreq-page-result"></p><hr><h2>表示文言の一括修正</h2><p>「営業中(未確認)」を「営業状況未確認」に修正します。手動で変更済みの店舗は対象外です（完全一致するもののみ更新）。何度実行しても安全です。</p><button class="button" id="anjod-fix-status">文言を修正</button><p id="anjod-fix-status-result"></p><hr><h2>情報源URLの一括反映</h2><p>同梱データに追加した情報源URLを、既存店舗に反映します（50件ずつ、情報源が未登録の店舗のみ。手動で入力済みの店舗は上書きしません）。</p><button class="button" id="anjod-fix-source">情報源URLを反映・再開</button><p role="status" id="anjod-fix-source-progress">処理済み：'.esc_html($srcOffset).'件</p><hr><h2>ららぽーと安城フロア情報の一括反映</h2><p>同梱データに追加した「ららぽーと安城」内店舗のフロア（1F〜4F）情報を、既存店舗の住所に反映します（50件ずつ、住所にフロア表記が無い店舗のみ）。</p><button class="button" id="anjod-fix-floor">フロア情報を反映・再開</button><p role="status" id="anjod-fix-floor-progress">処理済み：'.esc_html($floorOffset).'件</p><hr><h2>電話番号のハイフンを一括反映</h2><p>ハイフン無し表記の電話番号に、市外局番に応じたハイフンを補います（50件ずつ。既にハイフンが入っている番号は対象外＝手動修正済みは上書きしません）。</p><button class="button" id="anjod-fix-phone">電話番号を反映・再開</button><p role="status" id="anjod-fix-phone-progress">処理済み：'.esc_html($phoneOffset).'件</p></div>';
    wp_enqueue_script('anjod-import',plugins_url('import.js',__FILE__),array(),(string)filemtime(__DIR__.'/import.js'),true);
    wp_localize_script('anjod-import','anjodImport',array('url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('anjod_import')));
}
function anjod_authorize(){
    if(!current_user_can('manage_options'))wp_send_json_error('管理者権限が必要です。',403);
    check_ajax_referer('anjod_import','nonce');
}
add_action('wp_ajax_anjod_import',function(){
    anjod_authorize();
    // Atomic option insert prevents simultaneous batches in separate tabs.
    if(!add_option('anjod_import_lock',time(),'','no')){
        $t=(int)get_option('anjod_import_lock');
        if($t<time()-180){delete_option('anjod_import_lock');}
        wp_send_json_error('取り込み処理中です。数分後に再開してください。',409);
    }
    try {
        $rows=json_decode(file_get_contents(__DIR__.'/data.json'),true);
        if(!is_array($rows))throw new Exception('同梱データを読み込めません。');
        $offset=(int)get_option('anjod_import_offset',0);$end=min($offset+50,count($rows));
        for($i=$offset;$i<$end;$i++){
            $r=$rows[$i];if(empty($r['name'])||empty($r['_migration_id']))throw new Exception('データ形式が不正です。');
            $key=sanitize_text_field($r['_migration_id']);
            $existing=get_posts(array('post_type'=>'anjod_shop','post_status'=>array('publish','draft','pending','private','future','trash'),'fields'=>'ids','numberposts'=>1,'meta_key'=>'_anjod_migration_id','meta_value'=>$key));
            if(!$existing){
                $meta=array('_anjod_migration_id'=>$key);
                foreach(anjod_fields() as $k=>$label){$v=isset($r[$k])?(string)$r[$k]:'';$meta['_anjod_'.$k]=$k==='source'?esc_url_raw($v):($k==='note'?sanitize_textarea_field($v):sanitize_text_field($v));}
                $id=wp_insert_post(array('post_type'=>'anjod_shop','post_status'=>'publish','post_title'=>sanitize_text_field($r['name']),'post_content'=>'','meta_input'=>$meta),true);
                if(is_wp_error($id))throw new Exception($id->get_error_message());
                if(!empty($r['category'])){
                    $term=wp_set_object_terms($id,sanitize_text_field($r['category']),'anjod_category');
                    if(is_wp_error($term))throw new Exception($term->get_error_message());
                }
            }
            update_option('anjod_import_offset',$i+1,false);
        }
        delete_option('anjod_import_lock');
        wp_send_json_success(array('offset'=>$end,'total'=>count($rows),'done'=>$end===count($rows),'published'=>(int)wp_count_posts('anjod_shop')->publish));
    } catch(Throwable $e){delete_option('anjod_import_lock');wp_send_json_error($e->getMessage(),500);}
});
add_action('wp_ajax_anjod_fix_status',function(){
    anjod_authorize();
    global $wpdb;
    $n=$wpdb->update($wpdb->postmeta,array('meta_value'=>'営業状況未確認'),array('meta_key'=>'_anjod_status','meta_value'=>'営業中(未確認)'));
    wp_send_json_success(array('updated'=>(int)$n));
});
add_action('wp_ajax_anjod_fix_source',function(){
    anjod_authorize();
    if(!add_option('anjod_fix_source_lock',time(),'','no')){
        $t=(int)get_option('anjod_fix_source_lock');
        if($t<time()-180){delete_option('anjod_fix_source_lock');}
        wp_send_json_error('処理中です。数分後に再開してください。',409);
    }
    try{
        $rows=json_decode(file_get_contents(__DIR__.'/data.json'),true);
        if(!is_array($rows))throw new Exception('同梱データを読み込めません。');
        // Restart from the top whenever data.json's row count changes, so newly added rows aren't skipped by a stale offset.
        if((int)get_option('anjod_fix_source_total',0)!==count($rows)){
            update_option('anjod_fix_source_offset',0,false);
            update_option('anjod_fix_source_total',count($rows),false);
        }
        $offset=(int)get_option('anjod_fix_source_offset',0);$end=min($offset+50,count($rows));
        $updated=0;
        for($i=$offset;$i<$end;$i++){
            $r=$rows[$i];
            if(empty($r['_migration_id'])||empty($r['source']))continue;
            $key=sanitize_text_field($r['_migration_id']);
            $existing=get_posts(array('post_type'=>'anjod_shop','post_status'=>array('publish','draft','pending','private','future','trash'),'fields'=>'ids','numberposts'=>1,'meta_key'=>'_anjod_migration_id','meta_value'=>$key));
            if($existing){
                $postId=$existing[0];
                if(get_post_meta($postId,'_anjod_source',true)==='')
                {update_post_meta($postId,'_anjod_source',esc_url_raw($r['source']));$updated++;}
            }
        }
        update_option('anjod_fix_source_offset',$end,false);
        delete_option('anjod_fix_source_lock');
        wp_send_json_success(array('offset'=>$end,'total'=>count($rows),'done'=>$end===count($rows),'updated'=>$updated));
    }catch(Throwable $e){delete_option('anjod_fix_source_lock');wp_send_json_error($e->getMessage(),500);}
});
add_action('wp_ajax_anjod_fix_floor',function(){
    anjod_authorize();
    if(!add_option('anjod_fix_floor_lock',time(),'','no')){
        $t=(int)get_option('anjod_fix_floor_lock');
        if($t<time()-180){delete_option('anjod_fix_floor_lock');}
        wp_send_json_error('処理中です。数分後に再開してください。',409);
    }
    try{
        $rows=json_decode(file_get_contents(__DIR__.'/data.json'),true);
        if(!is_array($rows))throw new Exception('同梱データを読み込めません。');
        if((int)get_option('anjod_fix_floor_total',0)!==count($rows)){
            update_option('anjod_fix_floor_offset',0,false);
            update_option('anjod_fix_floor_total',count($rows),false);
        }
        $offset=(int)get_option('anjod_fix_floor_offset',0);$end=min($offset+50,count($rows));
        $updated=0;
        $hasFloor='/(B?\d+)\s*(?:F|階)/ui';
        for($i=$offset;$i<$end;$i++){
            $r=$rows[$i];
            if(empty($r['_migration_id'])||empty($r['address']))continue;
            if(strpos($r['address'],'ららぽーと')===false)continue;
            if(!preg_match($hasFloor,$r['address']))continue;
            $key=sanitize_text_field($r['_migration_id']);
            $existing=get_posts(array('post_type'=>'anjod_shop','post_status'=>array('publish','draft','pending','private','future','trash'),'fields'=>'ids','numberposts'=>1,'meta_key'=>'_anjod_migration_id','meta_value'=>$key));
            if($existing){
                $postId=$existing[0];
                $current=get_post_meta($postId,'_anjod_address',true);
                if($current!==''&&!preg_match($hasFloor,$current))
                {update_post_meta($postId,'_anjod_address',sanitize_text_field($r['address']));$updated++;}
            }
        }
        update_option('anjod_fix_floor_offset',$end,false);
        delete_option('anjod_fix_floor_lock');
        wp_send_json_success(array('offset'=>$end,'total'=>count($rows),'done'=>$end===count($rows),'updated'=>$updated));
    }catch(Throwable $e){delete_option('anjod_fix_floor_lock');wp_send_json_error($e->getMessage(),500);}
});
add_action('wp_ajax_anjod_fix_phone',function(){
    anjod_authorize();
    if(!add_option('anjod_fix_phone_lock',time(),'','no')){
        $t=(int)get_option('anjod_fix_phone_lock');
        if($t<time()-180){delete_option('anjod_fix_phone_lock');}
        wp_send_json_error('処理中です。数分後に再開してください。',409);
    }
    try{
        $rows=json_decode(file_get_contents(__DIR__.'/data.json'),true);
        if(!is_array($rows))throw new Exception('同梱データを読み込めません。');
        if((int)get_option('anjod_fix_phone_total',0)!==count($rows)){
            update_option('anjod_fix_phone_offset',0,false);
            update_option('anjod_fix_phone_total',count($rows),false);
        }
        $offset=(int)get_option('anjod_fix_phone_offset',0);$end=min($offset+50,count($rows));
        $updated=0;
        for($i=$offset;$i<$end;$i++){
            $r=$rows[$i];
            if(empty($r['_migration_id'])||empty($r['phone'])||strpos($r['phone'],'-')===false)continue;
            $digitsOnly=str_replace('-','',$r['phone']);
            $key=sanitize_text_field($r['_migration_id']);
            $existing=get_posts(array('post_type'=>'anjod_shop','post_status'=>array('publish','draft','pending','private','future','trash'),'fields'=>'ids','numberposts'=>1,'meta_key'=>'_anjod_migration_id','meta_value'=>$key));
            if($existing){
                $postId=$existing[0];
                $current=get_post_meta($postId,'_anjod_phone',true);
                if($current!==''&&strpos($current,'-')===false&&$current===$digitsOnly)
                {update_post_meta($postId,'_anjod_phone',sanitize_text_field($r['phone']));$updated++;}
            }
        }
        update_option('anjod_fix_phone_offset',$end,false);
        delete_option('anjod_fix_phone_lock');
        wp_send_json_success(array('offset'=>$end,'total'=>count($rows),'done'=>$end===count($rows),'updated'=>$updated));
    }catch(Throwable $e){delete_option('anjod_fix_phone_lock');wp_send_json_error($e->getMessage(),500);}
});
add_action('wp_ajax_anjod_page',function(){
    anjod_authorize();
    $rows=json_decode(file_get_contents(__DIR__.'/data.json'),true);
    if(!is_array($rows)||(int)get_option('anjod_import_offset',0)<count($rows))wp_send_json_error('先に取り込みを完了してください。',400);
    $id=(int)get_option('anjod_directory_page',0);
    if(!$id||!get_post($id)){
        $id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'安城ナビ','post_name'=>'anjo','post_content'=>'<!-- wp:shortcode -->[anjo_directory]<!-- /wp:shortcode -->'),true);
        if(is_wp_error($id))wp_send_json_error($id->get_error_message(),500);
        update_option('anjod_directory_page',$id,false);
    }
    wp_send_json_success(array('url'=>get_permalink($id),'edit'=>get_edit_post_link($id,'raw')));
});
add_action('wp_ajax_anjod_fixreq_page',function(){
    anjod_authorize();
    $id=(int)get_option('anjod_fixreq_page',0);
    if(!$id||!get_post($id)){
        $id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'情報修正はこちら','post_name'=>'anjo-fix','post_content'=>'<!-- wp:shortcode -->[anjo_correction_form]<!-- /wp:shortcode -->'),true);
        if(is_wp_error($id))wp_send_json_error($id->get_error_message(),500);
        update_option('anjod_fixreq_page',$id,false);
    }
    wp_send_json_success(array('url'=>get_permalink($id),'edit'=>get_edit_post_link($id,'raw')));
});
add_action('wp_enqueue_scripts',function(){
    if(is_singular('anjod_shop')||(is_singular()&&has_shortcode(get_post_field('post_content',get_queried_object_id()),'anjo_directory'))||(is_singular()&&has_shortcode(get_post_field('post_content',get_queried_object_id()),'anjo_correction_form'))){wp_enqueue_style('anjod',plugins_url('directory.css',__FILE__),array(),(string)filemtime(__DIR__.'/directory.css'));}
});
// Keep the browser-tab title short on the directory page instead of WordPress concatenating sitename + tagline + description.
add_filter('document_title_parts',function($parts){
    if(is_singular()&&has_shortcode(get_post_field('post_content',get_queried_object_id()),'anjo_directory')){
        $parts=array('title'=>'安城ナビ｜安城市のお店・企業を検索');
    }
    if(is_singular('anjod_shop')){
        $id=get_queried_object_id();
        $town=anjod_town_from_address(get_post_meta($id,'_anjod_address',true));
        $title=get_the_title($id).' | '.($town?:'安城市').' | 安城ナビ';
        $parts=array('title'=>$title);
    }
    return $parts;
});
function anjod_town_from_address($address){
    $address=(string)$address;
    if(preg_match('/(安城市[^0-9０-９\-－]*)/u',$address,$m)){
        $town=trim($m[1]);
        return $town!=='安城市'?$town:'安城市';
    }
    return '';
}
// Sort by title while ignoring a leading corporate-form prefix like (有)(株) so 50-on order isn't broken by symbols.
add_filter('posts_orderby',function($orderby,$q){
    if(!$q->get('anjod_search')||$q->get('orderby')!=='title')return $orderby;
    global $wpdb;
    $prefixes=array('(有)','(株)','（医）','（有)','（有）','（株）','（資）');
    $in=implode(',',array_fill(0,count($prefixes),'%s'));
    $expr=$wpdb->prepare("IF(LEFT({$wpdb->posts}.post_title,3) IN ($in), TRIM(SUBSTRING({$wpdb->posts}.post_title,4)), {$wpdb->posts}.post_title)",$prefixes);
    $order=$q->get('order')?:'ASC';
    return "$expr $order";
},10,2);
// Limit extended searching to our directory query, including current custom fields.
add_filter('posts_search',function($search,$q){
    global $wpdb;if(!$q->get('anjod_search'))return $search;
    $s=$q->get('s');if($s==='')return $search;
    $parts=preg_split('/[\s　]+/u',$s,-1,PREG_SPLIT_NO_EMPTY);$sql='';
    foreach($parts as $part){$like='%'.$wpdb->esc_like($part).'%';
        $sql.=$wpdb->prepare(" AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} am WHERE am.post_id={$wpdb->posts}.ID AND am.meta_key IN ('_anjod_address','_anjod_phone','_anjod_keywords') AND am.meta_value LIKE %s))",$like,$like,$like);
    }
    return $sql;
},10,2);
function anjod_category_emoji($name){
    $map=array('サービス・レジャー'=>'🎡','建設・工業'=>'🏗️','飲食・仕出し'=>'🍴','医療・暮らし'=>'🏥','ファッション・美容'=>'👗','自動車・運送'=>'🚗','ショップ'=>'🛍️','食品'=>'🍞','小売'=>'🛒');
    return isset($map[$name])?$map[$name]:'🏪';
}
function anjod_category_colors($name){
    $map=array(
        'サービス・レジャー'=>array('#fff1e0','#b3661b'),
        '建設・工業'=>array('#eef1f5','#55627a'),
        '飲食・仕出し'=>array('#ffe9dc','#c1541f'),
        '医療・暮らし'=>array('#fde8ef','#b2456b'),
        'ファッション・美容'=>array('#eef0fc','#4a54b0'),
        '自動車・運送'=>array('#e3f0fd','#1f6fb2'),
        'ショップ'=>array('#fff7de','#a67c00'),
        '食品'=>array('#f3e9da','#8a5a26'),
        '小売'=>array('#eef2f5','#56707a'),
    );
    return isset($map[$name])?$map[$name]:array('#edf8f5','#00695f');
}
function anjod_card($id){
    $v=array();foreach(anjod_fields() as $k=>$label)$v[$k]=get_post_meta($id,'_anjod_'.$k,true);
    $terms=get_the_terms($id,'anjod_category');$category=$terms&&!is_wp_error($terms)?implode(' / ',wp_list_pluck($terms,'name')):'';
    $categoryEmoji=$terms&&!is_wp_error($terms)&&$terms?anjod_category_emoji($terms[0]->name):'';
    list($catBg,$catText)=$terms&&!is_wp_error($terms)&&$terms?anjod_category_colors($terms[0]->name):array('#edf8f5','#00695f');
    ob_start(); ?>
    <article class="anjod-card" style="--cat-line:<?php echo esc_attr($catText); ?>">
      <p class="anjod-tag" style="background:<?php echo esc_attr($catBg); ?>;color:<?php echo esc_attr($catText); ?>"><?php echo esc_html(($categoryEmoji?$categoryEmoji.' ':'').$category); ?></p>
      <h3><a href="<?php echo esc_url(get_permalink($id)); ?>"><?php echo esc_html(get_the_title($id)); ?></a></h3>
      <p class="anjod-status"><?php echo esc_html($v['status']?:'営業状況要確認'); ?></p>
      <dl><dt>住所</dt><dd><?php echo esc_html($v['address']); ?></dd><dt>電話</dt><dd><?php echo esc_html($v['phone']?:'要確認'); ?></dd></dl>
      <?php $features=get_the_terms($id,'anjod_tag'); if($features&&!is_wp_error($features)): ?><ul class="anjod-features"><?php foreach($features as $f){ ?><li><?php echo esc_html($f->name); ?></li><?php } ?></ul><?php endif; ?>
      <?php if(is_singular('anjod_shop')&&$v['address']): ?><div class="anjod-map"><iframe src="https://www.google.com/maps?q=<?php echo rawurlencode($v['address']); ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?php echo esc_attr(get_the_title($id)); ?>の地図"></iframe></div><?php endif; ?>
      <?php if($v['checkedAt']): ?><p>情報確認日：<?php echo esc_html($v['checkedAt']); ?></p><?php elseif($v['reviewedAt']): ?><p>調査日：<?php echo esc_html($v['reviewedAt']); ?>（営業状況は要確認）</p><?php endif; ?>
      <?php if($v['note']): ?><p class="anjod-note"><?php echo nl2br(esc_html($v['note'])); ?></p><?php endif; ?>
      <?php if($v['source']): ?><a href="<?php echo esc_url($v['source']); ?>" target="_blank" rel="noopener noreferrer">情報源：<?php echo esc_html($v['sourceLabel']?:'掲載元を確認'); ?> ↗</a><?php elseif($v['sourceLabel']): ?><p>情報源：<?php echo esc_html($v['sourceLabel']); ?>（URL未登録）</p><?php endif; ?>
    </article>
    <?php return ob_get_clean();
}
function anjod_hero_svg(){
    return '<svg viewBox="0 0 420 260" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="安城の街並みのイラスト">
      <circle cx="362" cy="52" r="26" fill="#ffd27a"/>
      <path d="M300 46 q8 -9 16 0 q8 -9 16 0" stroke="#15334a" stroke-width="2.5" fill="none" stroke-linecap="round"/>
      <path d="M330 68 q6 -7 12 0 q6 -7 12 0" stroke="#15334a" stroke-width="2" fill="none" stroke-linecap="round"/>
      <rect x="0" y="205" width="420" height="55" fill="#d8f0e6"/>
      <rect x="38" y="150" width="10" height="58" rx="4" fill="#a9784f"/>
      <circle cx="43" cy="142" r="28" fill="#5fae86"/>
      <circle cx="18" cy="160" r="18" fill="#6bbd92"/>
      <circle cx="68" cy="160" r="18" fill="#6bbd92"/>
      <rect x="108" y="122" width="68" height="86" fill="#fff" stroke="#15334a" stroke-width="3"/>
      <polygon points="103,122 142,94 181,122" fill="#e8823c" stroke="#15334a" stroke-width="3" stroke-linejoin="round"/>
      <rect x="128" y="160" width="28" height="48" fill="#15334a"/>
      <rect x="118" y="136" width="14" height="14" fill="#008585"/>
      <rect x="152" y="136" width="14" height="14" fill="#008585"/>
      <rect x="188" y="92" width="58" height="116" fill="#fff" stroke="#15334a" stroke-width="3"/>
      <rect x="188" y="92" width="58" height="13" fill="#008585"/>
      <rect x="198" y="116" width="13" height="13" fill="#008585"/>
      <rect x="222" y="116" width="13" height="13" fill="#008585"/>
      <rect x="198" y="145" width="13" height="13" fill="#008585"/>
      <rect x="222" y="145" width="13" height="13" fill="#008585"/>
      <rect x="207" y="176" width="20" height="32" fill="#15334a"/>
      <rect x="252" y="132" width="78" height="76" fill="#fff" stroke="#15334a" stroke-width="3"/>
      <polygon points="247,132 291,104 335,132" fill="#5fae86" stroke="#15334a" stroke-width="3" stroke-linejoin="round"/>
      <rect x="277" y="170" width="28" height="38" fill="#15334a"/>
      <rect x="262" y="146" width="14" height="14" fill="#ffd27a"/>
      <rect x="296" y="146" width="14" height="14" fill="#ffd27a"/>
      <circle cx="196" cy="228" r="7" fill="#e8823c"/>
      <rect x="190" y="235" width="12" height="20" rx="5" fill="#15334a"/>
      <circle cx="226" cy="231" r="6" fill="#008585"/>
      <rect x="221" y="237" width="10" height="17" rx="5" fill="#5fae86"/>
    </svg>';
}
add_shortcode('anjo_directory',function(){
    $search=isset($_GET['anjo_q'])&&is_scalar($_GET['anjo_q'])?sanitize_text_field(wp_unslash($_GET['anjo_q'])):'';
    $cat=isset($_GET['anjo_cat'])?absint($_GET['anjo_cat']):0;$page=isset($_GET['anjo_page'])?max(1,absint($_GET['anjo_page'])):1;
    $tagIds=isset($_GET['anjo_tag'])&&is_array($_GET['anjo_tag'])?array_map('absint',wp_unslash($_GET['anjo_tag'])):array();
    $args=array('post_type'=>'anjod_shop','post_status'=>'publish','posts_per_page'=>18,'paged'=>$page,'orderby'=>'title','order'=>'ASC','s'=>$search,'anjod_search'=>true);
    $taxQuery=array();
    if($cat)$taxQuery[]=array('taxonomy'=>'anjod_category','field'=>'term_id','terms'=>$cat);
    foreach($tagIds as $tid)if($tid)$taxQuery[]=array('taxonomy'=>'anjod_tag','field'=>'term_id','terms'=>$tid);
    if($taxQuery){$taxQuery['relation']='AND';$args['tax_query']=$taxQuery;}
    $q=new WP_Query($args);$terms=get_terms(array('taxonomy'=>'anjod_category','hide_empty'=>true));$tags=get_terms(array('taxonomy'=>'anjod_tag','hide_empty'=>false));$base=get_permalink(get_queried_object_id());
    ob_start(); ?>
    <section class="anjod"><header class="anjod-hero">
      <div class="anjod-hero-text">
        <p>ANJO LOCAL BUSINESS GUIDE</p>
        <h2>安城のお店と企業を、<br>いまの情報で探す。</h2>
        <p>食事、買い物、暮らしのサービス、地域の企業を、町名や業種から探せます。</p>
        <strong><?php echo esc_html(number_format_i18n((int)wp_count_posts('anjod_shop')->publish)); ?>件の店舗・企業情報</strong>
      </div>
      <div class="anjod-hero-illust" aria-hidden="true"><?php echo anjod_hero_svg(); ?></div>
    </header>
    <form class="anjod-form" method="get" action="<?php echo esc_url($base); ?>">
      <?php if(!get_option('permalink_structure')): ?><input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>"><?php endif; ?>
      <label>店名・町名・電話など<input type="search" name="anjo_q" value="<?php echo esc_attr($search); ?>" placeholder="例：カフェ、桜井町"></label>
      <label>業種<select name="anjo_cat"><option value="0">すべての業種</option><?php if(!is_wp_error($terms))foreach($terms as $t){echo '<option value="'.esc_attr($t->term_id).'" '.selected($cat,$t->term_id,false).'>'.esc_html(anjod_category_emoji($t->name).' '.$t->name).'（'.esc_html($t->count).'）</option>';} ?></select></label>
      <?php if(!is_wp_error($tags)&&$tags): ?><fieldset class="anjod-tags"><legend>特徴で絞り込む</legend><?php foreach($tags as $t){ ?><label class="anjod-tag-check"><input type="checkbox" name="anjo_tag[]" value="<?php echo esc_attr($t->term_id); ?>" <?php checked(in_array($t->term_id,$tagIds,true)); ?>><?php echo esc_html($t->name); ?>（<?php echo esc_html($t->count); ?>）</label><?php } ?></fieldset><?php endif; ?>
      <button type="submit">検索する</button><a href="<?php echo esc_url($base); ?>">条件をクリア</a>
    </form><p role="status"><?php echo esc_html(number_format_i18n($q->found_posts)); ?>件が見つかりました。</p>
    <div class="anjod-grid"><?php foreach($q->posts as $p)echo anjod_card($p->ID); ?></div>
    <?php if(!$q->found_posts)echo '<p>条件に合う店舗がありません。別の言葉で検索してください。</p>'; ?>
    <nav class="anjod-pages" aria-label="店舗一覧のページ切り替え"><?php echo wp_kses_post(paginate_links(array('base'=>add_query_arg('anjo_page','%#%',$base),'format'=>'','current'=>$page,'total'=>$q->max_num_pages,'add_args'=>array('anjo_q'=>$search,'anjo_cat'=>$cat,'anjo_tag'=>$tagIds),'prev_text'=>'前へ','next_text'=>'次へ'))); ?></nav>
    <footer class="anjod-policy"><h3>掲載情報について</h3><p>情報源・確認日は店舗ごとに異なります。「要確認」は閉店を意味しません。営業日時・電話番号などは、ご利用前に各店舗へご確認ください。</p></footer></section>
    <?php return ob_get_clean();
});
add_filter('the_content',function($content){if(is_singular('anjod_shop')&&in_the_loop()&&is_main_query())return '<div class="anjod">'.anjod_card(get_the_ID()).$content.'</div>';return $content;});

// --- Owner correction request form: submissions are queued for manual review, never auto-applied to shop data. ---
function anjodfix_fields(){
    return array('phone'=>'電話番号','hours'=>'営業時間・定休日','address'=>'住所','note'=>'その他・伝えたいこと');
}
function anjodfix_tag_options(){
    $terms=get_terms(array('taxonomy'=>'anjod_tag','hide_empty'=>false));
    return is_wp_error($terms)?array():wp_list_pluck($terms,'name');
}
add_action('add_meta_boxes',function(){add_meta_box('anjodfix_details','送信内容','anjodfix_metabox','anjod_fixreq','normal','high');});
function anjodfix_metabox($post){
    $shop=get_post_meta($post->ID,'_anjodfix_shop_name',true);
    $contactName=get_post_meta($post->ID,'_anjodfix_contact_name',true);
    $contactEmail=get_post_meta($post->ID,'_anjodfix_contact_email',true);
    echo '<table class="form-table"><tr><th>店舗名</th><td>'.esc_html($shop).'</td></tr>';
    foreach(anjodfix_fields() as $k=>$label){
        $v=get_post_meta($post->ID,'_anjodfix_'.$k,true);
        echo '<tr><th>'.esc_html($label).'</th><td>'.nl2br(esc_html($v)).'</td></tr>';
    }
    echo '<tr><th>特徴</th><td>'.esc_html(get_post_meta($post->ID,'_anjodfix_tags',true)).'</td></tr>';
    echo '<tr><th>連絡先</th><td>'.esc_html($contactName).($contactEmail?' / '.esc_html($contactEmail):'').'</td></tr></table>';
    echo '<p>内容を確認し、該当する店舗（<a href="'.esc_url(admin_url('edit.php?post_type=anjod_shop')).'">店舗情報一覧</a>から該当店舗を編集）に反映してください。「特徴」は該当店舗の編集画面右側にある「特徴」ボックスにタグとして追加してください。反映後はこの依頼をゴミ箱に移動して構いません。</p>';
}
add_filter('manage_anjod_fixreq_posts_columns',function($cols){
    return array('cb'=>$cols['cb'],'title'=>'受付日時','shop'=>'店舗名','phone'=>'電話番号','hours'=>'営業時間','tags'=>'特徴','contact'=>'連絡先');
});
add_action('manage_anjod_fixreq_posts_custom_column',function($col,$id){
    if($col==='shop')echo esc_html(get_post_meta($id,'_anjodfix_shop_name',true));
    if($col==='phone')echo esc_html(get_post_meta($id,'_anjodfix_phone',true));
    if($col==='hours')echo esc_html(get_post_meta($id,'_anjodfix_hours',true));
    if($col==='tags')echo esc_html(get_post_meta($id,'_anjodfix_tags',true));
    if($col==='contact'){
        $n=get_post_meta($id,'_anjodfix_contact_name',true);$e=get_post_meta($id,'_anjodfix_contact_email',true);
        echo esc_html($n).($e?' / '.esc_html($e):'');
    }
},10,2);

add_shortcode('anjo_correction_form',function(){
    if(isset($_GET['anjo_sent'])){
        return '<div class="anjod-fixform-done"><p>送信ありがとうございました。内容を確認のうえ、順次サイトに反映いたします。</p></div>';
    }
    ob_start(); ?>
    <div class="anjod-fixform">
    <p>掲載店舗の店主・ご担当者さまへ：電話番号・営業時間・住所などの最新情報を教えてください。内容を確認のうえ、安城ナビに反映します。</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="anjod_submit_fix">
        <?php wp_nonce_field('anjod_fix_submit','anjod_fix_nonce'); ?>
        <input type="hidden" name="anjod_fix_redirect" value="<?php echo esc_url(get_permalink()); ?>">
        <p style="position:absolute;left:-9999px;" aria-hidden="true"><label>そのままにしてください<input type="text" name="anjodfix_hp" tabindex="-1" autocomplete="off"></label></p>
        <label>店舗名（必須）<input type="text" name="shop_name" required></label>
        <label>電話番号<input type="text" name="phone"></label>
        <label>営業時間・定休日<input type="text" name="hours"></label>
        <label>住所<input type="text" name="address"></label>
        <fieldset class="anjod-tags"><legend>あてはまる特徴（任意）</legend><?php foreach(anjodfix_tag_options() as $t){ ?><label class="anjod-tag-check"><input type="checkbox" name="tags[]" value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></label><?php } ?></fieldset>
        <label>その他・伝えたいこと<textarea name="note"></textarea></label>
        <label>お名前・会社名（任意）<input type="text" name="contact_name"></label>
        <label>ご連絡先メールアドレス（任意・返信をご希望の場合）<input type="email" name="contact_email"></label>
        <button type="submit">この内容を送信する</button>
    </form>
    </div>
    <?php return ob_get_clean();
});

function anjod_handle_fix_submit(){
    if(!isset($_POST['anjod_fix_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['anjod_fix_nonce'])),'anjod_fix_submit')){
        wp_die('不正なリクエストです。<a href="'.esc_url(wp_get_referer()?:home_url()).'">戻る</a>');
    }
    $redirect=isset($_POST['anjod_fix_redirect'])?esc_url_raw(wp_unslash($_POST['anjod_fix_redirect'])):home_url();
    if(!empty($_POST['anjodfix_hp'])){
        wp_safe_redirect(add_query_arg('anjo_sent','1',$redirect));exit;
    }
    $shop=isset($_POST['shop_name'])?sanitize_text_field(wp_unslash($_POST['shop_name'])):'';
    if($shop===''){
        wp_die('店舗名を入力してください。<a href="javascript:history.back()">戻る</a>');
    }
    $fields=array(
        'phone'=>isset($_POST['phone'])?sanitize_text_field(wp_unslash($_POST['phone'])):'',
        'hours'=>isset($_POST['hours'])?sanitize_text_field(wp_unslash($_POST['hours'])):'',
        'address'=>isset($_POST['address'])?sanitize_text_field(wp_unslash($_POST['address'])):'',
        'note'=>isset($_POST['note'])?sanitize_textarea_field(wp_unslash($_POST['note'])):'',
    );
    $allowedTags=anjodfix_tag_options();
    $tags=isset($_POST['tags'])&&is_array($_POST['tags'])?array_values(array_intersect($allowedTags,array_map('sanitize_text_field',wp_unslash($_POST['tags'])))):array();
    $tagsText=implode('、',$tags);
    $contactName=isset($_POST['contact_name'])?sanitize_text_field(wp_unslash($_POST['contact_name'])):'';
    $contactEmail=(isset($_POST['contact_email'])&&is_email(wp_unslash($_POST['contact_email'])))?sanitize_email(wp_unslash($_POST['contact_email'])):'';

    $id=wp_insert_post(array('post_type'=>'anjod_fixreq','post_status'=>'pending','post_title'=>$shop.'からの修正依頼 '.current_time('Y-m-d H:i')),true);
    if(!is_wp_error($id)){
        update_post_meta($id,'_anjodfix_shop_name',$shop);
        foreach($fields as $k=>$v)update_post_meta($id,'_anjodfix_'.$k,$v);
        update_post_meta($id,'_anjodfix_tags',$tagsText);
        update_post_meta($id,'_anjodfix_contact_name',$contactName);
        update_post_meta($id,'_anjodfix_contact_email',$contactEmail);
        wp_mail(get_option('admin_email'),'【安城ナビ】店舗情報の修正依頼：'.$shop,
            "店舗名: {$shop}\n電話番号: {$fields['phone']}\n営業時間・定休日: {$fields['hours']}\n住所: {$fields['address']}\n特徴: {$tagsText}\nその他: {$fields['note']}\n連絡先: {$contactName} {$contactEmail}\n\n確認・反映: ".admin_url('edit.php?post_type=anjod_fixreq'));
    }
    wp_safe_redirect(add_query_arg('anjo_sent','1',$redirect));exit;
}
add_action('admin_post_anjod_submit_fix','anjod_handle_fix_submit');
add_action('admin_post_nopriv_anjod_submit_fix','anjod_handle_fix_submit');
