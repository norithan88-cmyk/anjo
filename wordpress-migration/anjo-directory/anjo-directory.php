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
}
add_action('init','anjod_init');
register_activation_hook(__FILE__,function(){anjod_init();flush_rewrite_rules();});
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
    echo '<div class="wrap"><h1>安城ナビ：データ取り込み</h1><p>同梱データを50件ずつ取り込みます。画面を閉じても再開できます。同じデータを二重登録せず、取り込み済み店舗の編集内容を上書きしません。</p><p>確認日と確認状況は元データのまま引き継ぎます。取り込みは営業状況の再検証ではありません。</p><button class="button button-primary" id="anjod-run">取り込みを開始・再開</button><p role="status" id="anjod-progress">処理済み：'.esc_html($offset).'件</p><hr><h2>検索ページを作成</h2><p>取り込み完了後に実行してください。固定ページ「安城ナビ」を公開します。トップページへの設定は「設定 → 表示設定」で選べます。</p><button class="button" id="anjod-page">検索ページを作成</button><p id="anjod-page-result"></p><hr><h2>表示文言の一括修正</h2><p>「営業中(未確認)」を「営業状況未確認」に修正します。手動で変更済みの店舗は対象外です（完全一致するもののみ更新）。何度実行しても安全です。</p><button class="button" id="anjod-fix-status">文言を修正</button><p id="anjod-fix-status-result"></p><hr><h2>情報源URLの一括反映</h2><p>同梱データに追加した情報源URLを、既存店舗に反映します（50件ずつ、情報源が未登録の店舗のみ。手動で入力済みの店舗は上書きしません）。</p><button class="button" id="anjod-fix-source">情報源URLを反映・再開</button><p role="status" id="anjod-fix-source-progress">処理済み：'.esc_html($srcOffset).'件</p></div>';
    wp_enqueue_script('anjod-import',plugins_url('import.js',__FILE__),array(),'1.0.0',true);
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
add_action('wp_enqueue_scripts',function(){
    if(is_singular('anjod_shop')||(is_singular()&&has_shortcode(get_post_field('post_content',get_queried_object_id()),'anjo_directory'))){wp_enqueue_style('anjod',plugins_url('directory.css',__FILE__),array(),'1.0.0');}
});
// Keep the browser-tab title short on the directory page instead of WordPress concatenating sitename + tagline + description.
add_filter('document_title_parts',function($parts){
    if(is_singular()&&has_shortcode(get_post_field('post_content',get_queried_object_id()),'anjo_directory')){
        $parts=array('title'=>'安城ナビ｜安城市のお店・企業を検索');
    }
    return $parts;
});
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
function anjod_card($id){
    $v=array();foreach(anjod_fields() as $k=>$label)$v[$k]=get_post_meta($id,'_anjod_'.$k,true);
    $terms=get_the_terms($id,'anjod_category');$category=$terms&&!is_wp_error($terms)?implode(' / ',wp_list_pluck($terms,'name')):'';
    ob_start(); ?>
    <article class="anjod-card">
      <p class="anjod-tag"><?php echo esc_html($category); ?></p>
      <h3><a href="<?php echo esc_url(get_permalink($id)); ?>"><?php echo esc_html(get_the_title($id)); ?></a></h3>
      <p class="anjod-status"><?php echo esc_html($v['status']?:'営業状況要確認'); ?></p>
      <dl><dt>住所</dt><dd><?php echo esc_html($v['address']); ?></dd><dt>電話</dt><dd><?php echo esc_html($v['phone']?:'要確認'); ?></dd></dl>
      <?php if($v['checkedAt']): ?><p>情報確認日：<?php echo esc_html($v['checkedAt']); ?></p><?php elseif($v['reviewedAt']): ?><p>調査日：<?php echo esc_html($v['reviewedAt']); ?>（営業状況は要確認）</p><?php endif; ?>
      <?php if($v['note']): ?><p class="anjod-note"><?php echo nl2br(esc_html($v['note'])); ?></p><?php endif; ?>
      <?php if($v['source']): ?><a href="<?php echo esc_url($v['source']); ?>" target="_blank" rel="noopener noreferrer">情報源：<?php echo esc_html($v['sourceLabel']?:'掲載元を確認'); ?> ↗</a><?php elseif($v['sourceLabel']): ?><p>情報源：<?php echo esc_html($v['sourceLabel']); ?>（URL未登録）</p><?php endif; ?>
    </article>
    <?php return ob_get_clean();
}
add_shortcode('anjo_directory',function(){
    $search=isset($_GET['anjo_q'])&&is_scalar($_GET['anjo_q'])?sanitize_text_field(wp_unslash($_GET['anjo_q'])):'';
    $cat=isset($_GET['anjo_cat'])?absint($_GET['anjo_cat']):0;$page=isset($_GET['anjo_page'])?max(1,absint($_GET['anjo_page'])):1;
    $args=array('post_type'=>'anjod_shop','post_status'=>'publish','posts_per_page'=>18,'paged'=>$page,'orderby'=>'title','order'=>'ASC','s'=>$search,'anjod_search'=>true);
    if($cat)$args['tax_query']=array(array('taxonomy'=>'anjod_category','field'=>'term_id','terms'=>$cat));
    $q=new WP_Query($args);$terms=get_terms(array('taxonomy'=>'anjod_category','hide_empty'=>true));$base=get_permalink(get_queried_object_id());
    ob_start(); ?>
    <section class="anjod"><header class="anjod-hero"><p>ANJO LOCAL BUSINESS GUIDE</p><h2>安城のお店と企業を、<br>いまの情報で探す。</h2><p>食事、買い物、暮らしのサービス、地域の企業を、町名や業種から探せます。</p><strong><?php echo esc_html(number_format_i18n((int)wp_count_posts('anjod_shop')->publish)); ?>件の店舗・企業情報</strong></header>
    <form class="anjod-form" method="get" action="<?php echo esc_url($base); ?>">
      <?php if(!get_option('permalink_structure')): ?><input type="hidden" name="page_id" value="<?php echo esc_attr(get_queried_object_id()); ?>"><?php endif; ?>
      <label>店名・町名・電話など<input type="search" name="anjo_q" value="<?php echo esc_attr($search); ?>" placeholder="例：カフェ、桜井町"></label>
      <label>業種<select name="anjo_cat"><option value="0">すべての業種</option><?php if(!is_wp_error($terms))foreach($terms as $t){echo '<option value="'.esc_attr($t->term_id).'" '.selected($cat,$t->term_id,false).'>'.esc_html($t->name).'（'.esc_html($t->count).'）</option>';} ?></select></label>
      <button type="submit">検索する</button><a href="<?php echo esc_url($base); ?>">条件をクリア</a>
    </form><p role="status"><?php echo esc_html(number_format_i18n($q->found_posts)); ?>件が見つかりました。</p>
    <div class="anjod-grid"><?php foreach($q->posts as $p)echo anjod_card($p->ID); ?></div>
    <?php if(!$q->found_posts)echo '<p>条件に合う店舗がありません。別の言葉で検索してください。</p>'; ?>
    <nav class="anjod-pages" aria-label="店舗一覧のページ切り替え"><?php echo wp_kses_post(paginate_links(array('base'=>add_query_arg('anjo_page','%#%',$base),'format'=>'','current'=>$page,'total'=>$q->max_num_pages,'add_args'=>array('anjo_q'=>$search,'anjo_cat'=>$cat),'prev_text'=>'前へ','next_text'=>'次へ'))); ?></nav>
    <footer class="anjod-policy"><h3>掲載情報について</h3><p>情報源・確認日は店舗ごとに異なります。「要確認」は閉店を意味しません。営業日時・電話番号などは、ご利用前に各店舗へご確認ください。</p></footer></section>
    <?php return ob_get_clean();
});
add_filter('the_content',function($content){if(is_singular('anjod_shop')&&in_the_loop()&&is_main_query())return '<div class="anjod">'.anjod_card(get_the_ID()).$content.'</div>';return $content;});
