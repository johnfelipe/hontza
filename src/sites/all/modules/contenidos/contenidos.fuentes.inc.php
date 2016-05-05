<?php
function contenidos_fuentes_callback(){
    global $user;
    //intelsat-2016
    drupal_access_denied();
    exit();
    $headers=array();
    $headers[0]='<input type="checkbox" id="my_select_all" name="my_select_all" class="my_select_all"/>';
    $headers[1]=array('data'=>t('Source'),'field'=>'node_title');
    //$headers[1]=array('data'=>t('Type'),'field'=>'node_type');
    $headers[2]=t('Related Channels');     
    $headers[3]=array('data'=>t('Group'),'field'=>'og_ancestry_group_nid');
    $headers[4]=array('data'=>t(t('Date')),'field'=>'node_changed');
    $headers[5]=t('Actions');
    $my_limit=30;
    
    $filter_fields=contenidos_fuentes_define_filter_fields();
   
    $where=array();
    $where[]='1';
    $where[]='users.uid = '.$user->uid;
    $where[]='node.type in ("supercanal", "fuentedapper", "fuentehtml")'; 
        
    if(!empty($filter_fields)){
       foreach($filter_fields as $k=>$f){
           $v=hontza_get_gestion_usuarios_filter_value($f,'contenidos_fuentes_filtro');
           if(!empty($v)){
                switch($f){
                    case 'grupo_nid':
                        $where[]='og_ancestry.group_nid='.$v;
                        break;
                    case 'text':
                        $where[]='(node.title LIKE "%%'.$v.'%%" OR node_revisions.body LIKE "%%'.$v.'%%")';
                        break;
                    /*case 'fecha_ini':
                    case 'fecha_fin':    
                        $where[]=hontza_get_usuarios_acceso_where_time('node.changed','contenidos_filtro','fecha_ini','fecha_fin');
                        break;*/
                }
           } 
       }
   }
    
    $where[]=hontza_get_usuarios_acceso_where_time('node.changed','contenidos_fuentes_filtro','fecha_inicio','fecha_fin');
    
    $sort='asc';
    $field='node_title';
    $is_numeric=0;
    if(isset($_REQUEST['sort']) && !empty($_REQUEST['sort'])){
        $sort=$_REQUEST['sort'];
    }
    if(isset($_REQUEST['order']) && !empty($_REQUEST['order'])){
        $order=$_REQUEST['order'];
        if($order==t('Source')){
            $field='node_title';
        }else if($order==t('Type')){
            $field='node_type';
        }else if($order==t('Date')){
            $field='node_changed';
        }
    }
    $sql='SELECT node.nid AS nid,
    node.title AS node_title,
    node.language AS node_language,
    node.type AS node_type,
    og_ancestry.nid AS og_ancestry_nid,
    node.created AS node_created,
    node.status AS node_status,
    node.uid AS node_uid,
    node_revisions.format AS node_revisions_format,
    node.changed AS node_changed,
    og_ancestry.group_nid AS og_ancestry_group_nid 
    FROM {node} node 
    INNER JOIN {users} users ON node.uid = users.uid 
    LEFT JOIN {og_ancestry} og_ancestry ON node.nid = og_ancestry.nid 
    LEFT JOIN {node_revisions} node_revisions ON node.vid = node_revisions.vid 
    WHERE '.implode(' AND ',$where).' 
    ORDER BY '.$field.' '.$sort; 
    //
    $res=db_query($sql);        
    
    $rows=array();
    $kont=0;
    while ($r = db_fetch_object($res)) {
      $rows[$kont]=array();
      $rows[$kont][0]='<input type="checkbox" id="txek_'.$r->nid.'" name="txek_nid['.$r->nid.']" class="bulk_txek" value="1">';
      $rows[$kont][1]=l(red_funciones_resumir_titulo($r->node_title),'node/'.$r->nid);
      $node_type_label=contenidos_fuentes_get_node_type_label($r->node_type);  
      //$rows[$kont][1]=$node_type_label;  
      $rows[$kont][2]=contenidos_fuentes_get_canales_html($r->nid);
      $grupo_nid=$r->og_ancestry_group_nid;
      $rows[$kont][3]=contenidos_get_grupo_link($grupo_nid,$grupo_title);
      $rows[$kont][4]=date('Y-m-d H:i',$r->node_changed);
      $rows[$kont][5]=array('data'=>contenidos_fuentes_define_acciones($r),'style'=>'white-space:nowrap;');
      $rows[$kont]['node_type_label']=$node_type_label;
      $rows[$kont]['grupo_title']=$grupo_title;
      $kont++;
    }
    //
    if($order==t('Type')){
        $rows=array_ordenatu($rows,'node_type_label', $sort,0);        
    }else if($order==t('Group')){
        //intelsat-2015     
        $rows=array_ordenatu($rows,'grupo_title', $sort,0);               
    }
    $rows=hontza_unset_array($rows,array('node_type_label','grupo_title')); 
    $rows=my_set_estrategia_pager($rows, $my_limit);
    if (count($rows)>0) {
        $output .= theme('table',$headers,$rows,array('class'=>'table_panel_admin_grupos'));
        $output .= theme('pager', NULL, $my_limit);    
    }else {
        $output.= '<div id="first-time">' .t('There are no contents'). '</div>';
    }
    //
    $output=contenidos_my_menu().contenidos_fuentes_define_filtro().drupal_get_form('contenidos_fuentes_bulk_form',array($output));
    return $output;
}
function contenidos_fuentes_get_node_type_label($node_type){
    if($node_type=='fuentehtml'){
        $s=t('Page2RSS Source');
    }else if($node_type=='supercanal'){
        $s=t('Pipe Source');
    }else if($node_type=='fuentedapper'){
        $s=t('Dapper Source');
    }
    //
    return $s;
}
function contenidos_fuentes_define_acciones($r,$destination='destination=contenidos'){
    return contenidos_define_acciones($r,'destination=contenidos/fuentes');
}
function contenidos_fuentes_define_filtro(){
    my_add_buscar_js();
    return drupal_get_form('contenidos_fuentes_filtro_form');
}
function contenidos_fuentes_filtro_form(){
    $fs_title=t('Search');
    if(contenidos_fuentes_is_filter_activated()){
        $fs_title=t('Filter Activated');
        $class='file_buscar_fs_vigilancia_class fs_search_activated';
    }else{
        $fs_title=t('Filter');
        $class='file_buscar_fs_vigilancia_class';        
    }    
    //           
    $form=array();
    $form['file_buscar_fs']=array('#type'=>'fieldset','#title'=>$fs_title,'#attributes'=>array('id'=>'file_buscar_fs','class'=>$class));
    /*$form['file_buscar_fs']['node_type']=array(
        '#type'=>'select',
        '#title'=>t('Select the type of Source'),
        '#options'=>contenidos_fuentes_define_node_type_options(),
        '#default_value'=>contenidos_fuentes_get_filter_value($field),
    );*/
    contenidos_add_filter_form_fields('contenidos_fuentes_filtro',$form);
    $form['file_buscar_fs']['my_submit']=array(
        '#type'=>'submit',
        '#value'=>t('Apply'),
    );
    $form['file_buscar_fs']['limpiar']=array(
        '#type'=>'submit',
        '#name'=>'limpiar',
        '#value'=>t('Reset'),
    );
    return $form;
}
function contenidos_fuentes_define_node_type_options(){
    $result=array();
    $result['']='';
    $result['fuentehtml']=t('Page2RSS Source');
    $result['supercanal']=t('Pipe Source');
    $result['fuentedapper']=t('Dapper Source');
    return $result;
}
function contenidos_fuentes_filtro_form_submit(&$form, &$form_state){
    if(isset($form_state['clicked_button']) && !empty($form_state['clicked_button'])){
        $name=$form_state['clicked_button']['#name'];
        $key='contenidos_fuentes_filtro';
        if(strcmp($name,'limpiar')==0){
            if(isset($_SESSION[$key]['filter']) && !empty($_SESSION[$key]['filter'])){
                unset($_SESSION[$key]['filter']);
            }
        }else{
            $_SESSION[$key]['filter']=array();
            $fields=contenidos_fuentes_define_filter_fields();
            if(count($fields)>0){
                foreach($fields as $i=>$f){
                    $v=$form_state['values'][$f];
                    if(!empty($v)){
                        $_SESSION[$key]['filter'][$f]=$v;
                    }
                }
            }            
        }
    } 
}
function contenidos_fuentes_define_filter_fields(){
    $result=array('grupo_nid','text','fecha_inicio','fecha_fin');
    return $result;
}
function contenidos_fuentes_is_filter_activated(){
    $fields=contenidos_fuentes_define_filter_fields();
    if(count($fields)>0){
        foreach($fields as $i=>$f){
            if(isset($_SESSION['contenidos_fuentes_filtro']['filter'][$f]) && !empty($_SESSION['contenidos_fuentes_filtro']['filter'][$f])){
                return 1;
            }
        }
    }
    return 0;
}
function contenidos_fuentes_get_filter_value($field){
    return hontza_get_gestion_usuarios_filter_value($field,'contenidos_canales_filtro');
}
function contenidos_fuentes_get_canales_html($fuente_nid){
    $html=array();
    //$html[]='<div class="item-list">';
    //$html[]='<ul>';
    $content_field_nid_fuente_canal_array=hontza_get_content_field_nid_fuente_canal($fuente_nid);
    if(!empty($content_field_nid_fuente_canal_array)){
        foreach($content_field_nid_fuente_canal_array as $i=>$row){
            //$canal=node_load($row->field_nid_fuente_canal_value);
            $canal=node_load($row->nid);
            if(isset($canal->nid) && !empty($canal->nid)){
                //$html[]='<li>'.l($canal->title,'node/'.$canal->nid).'</li>';
                $html[]=l($canal->title,'node/'.$canal->nid);
            }
        }
    }
    /*$html[]='</ul>';
    $html[]='</div>';
    return implode('',$html);*/
    return implode('<br>',$html);
}
function contenidos_fuentes_bulk_form(){
    $form=array();
    $vars=func_get_args();
    //
    $form['my_bulk_operations_fs']=array(
      '#type'=>'fieldset',
      '#title'=>t('Bulk Actions'),
    );
    //
    panel_admin_items_add_bulk_operations_form_fields($form,1);
    //
    my_add_noticias_publicas_select_all_js();
    $form['my_table']=array('#value'=>$vars[1][0]);
    //
    return $form;
}
function contenidos_fuentes_bulk_form_submit($form, &$form_state) {
    panel_admin_items_gestion_items_bulk_submit($form,$form_state,'contenidos_fuentes');
}
function contenidos_fuentes_delete_fuentes_confirm_form(){
    $form=panel_admin_grupos_get_grupos_confirm('delete_node','block_panel_admin_fuentes_nid_array','contenidos/fuentes');
    return $form;
}
function contenidos_fuentes_delete_fuentes_confirm_form_submit(&$form, &$form_state){
    global $base_url;
    $url=$base_url.'/contenidos/fuentes';
    call_bulk_confirm_form_submit($form,$form_state,'delete_node',$url,true);
}
function contenidos_fuentes_publish_post_fuentes_confirm_form(){
    $form=panel_admin_grupos_get_grupos_confirm('publish_post','block_panel_admin_fuentes_nid_array','contenidos/fuentes');
    return $form;
}
function contenidos_fuentes_unpublish_post_fuentes_confirm_form(){
    $form=panel_admin_grupos_get_grupos_confirm('unpublish_post','block_panel_admin_fuentes_nid_array','contenidos/fuentes');
    return $form;
}
function contenidos_fuentes_publish_post_fuentes_confirm_form_submit(&$form, &$form_state){
    global $base_url;
    $url='contenidos/fuentes';
    call_bulk_confirm_form_submit($form,$form_state,'publish_post',$url,true);    
}
function contenidos_fuentes_unpublish_post_fuentes_confirm_form_submit(&$form, &$form_state){
    global $base_url;
    $url='contenidos/fuentes';
    call_bulk_confirm_form_submit($form,$form_state,'unpublish_post',$url,true);    
}