<?php
require_once "./model/vendor/autoload.php";

// ************** Reading excel file *******************
use PhpOffice\PhpSpreadsheet\Spreadsheet;
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
$spreadsheet = $reader->load("Daily Inventory.xls");
$sheetData = $spreadsheet->getActiveSheet()->toArray();

$i=0;
unset($sheetData[0]);
$backorder_skus = [];
foreach ($sheetData as $t) 
{
    if($t[7] == "Backorder")
    {
        array_push($backorder_skus, $t[0]);
    }
}
if(isset($_POST['start_working']))
{
    // ************** Processing Product Data ****************
    $request = new HTTP_Request2();
    $sh_api_user = "36cc91591516fc74a7e05bbe7060bece";
    $sh_api_pw = "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";
    $dev_shop_info = "csa-medical-supply.myshopify.com";
    $req_begin = "https://";

    $total_arr = [];


    function request_data($req_body)
    {
        global $request;
        global $sh_api_user;
        global $sh_api_pw;
        global $dev_shop_info;
        global $req_begin;

        $tmp_array = [];
        $request->setMethod(HTTP_Request2::METHOD_GET);
        $request->setConfig(array(
            'follow_redirects' => TRUE
        ));
        $request->setAuth($sh_api_user, $sh_api_pw, HTTP_Request2::AUTH_BASIC);
        $url = $req_begin . $dev_shop_info . $req_body;

        $request->setUrl($url);
        $res = $request->send()->getBody();
        $res = json_decode($res);

        return $res;

    }

    function request_product($method, $url, $page_info="", $param = []){
        global $request;
        global $sh_api_user;
        global $sh_api_pw;
        global $dev_shop_info;
        global $req_begin;

        $return_arr = [];
        $client = new \GuzzleHttp\Client();
        $url = 'https://'.$sh_api_user.':'.$sh_api_pw.'@'.$dev_shop_info.'/admin/api/2021-07/'.$url;
        $parameters = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ];
        if(!empty($param)){ $parameters['json'] = $param;}
        $response = $client->request($method, $url,$parameters);
        $responseHeaders = $response->getHeaders();
        $tokenType = 'next';
        if(array_key_exists('Link',$responseHeaders)){
            $link = $responseHeaders['Link'][0];
            $tokenType  = strpos($link,'rel="next') !== false ? "next" : "previous";
            $tobeReplace = ["<",">",'rel="next"',";",'rel="previous"'];
            $tobeReplaceWith = ["","","",""];
            parse_str(parse_url(str_replace($tobeReplace,$tobeReplaceWith,$link),PHP_URL_QUERY),$op);
            $pageToken = trim($op['page_info']);

            array_push($return_arr, $pageToken);
        }
        $rateLimit = explode('/', $responseHeaders["X-Shopify-Shop-Api-Call-Limit"][0]);
        $usedLimitPercentage = (100*$rateLimit[0])/$rateLimit[1];
        if($usedLimitPercentage > 95){sleep(5);}
        $responseBody = json_decode($response->getBody(),true);
        $r['resource'] =  (is_array($responseBody) && count($responseBody) > 0) ? array_shift($responseBody) : $responseBody;
        $r[$tokenType]['page_token'] = isset($pageToken) ? $pageToken : null;

        array_push($return_arr, $r);
        return $return_arr;
    }

    // ******************** Get all locations and save them to array ****************
    $locations = request_data("/admin/api/2021-07/locations.json");
    $loc_arry = [];

    $tmp_loc_data = $locations->locations;
    for($i = 0; $i < count($tmp_loc_data); $i++)
    {
        array_push($loc_arry, $tmp_loc_data[$i]->id);
    }

   // ******************** Get total product count *************************
    $count = request_data("/admin/api/2021-07/products/count.json");
    $count = intval($count->count);

    $page_count = round($count / 250, 0, PHP_ROUND_HALF_DOWN);


    // ********************* Get all product ********************************
    // api = "/admin/api/2021-07/products.json?limit=250"
    $page_info="";
    $j = 0;
    for($i = 0; $i < $page_count; $i++)
    {
        if($i == 0) 
        {
            $tmp_token = request_product("GET", "products.json?limit=250");
            $page_info = $tmp_token[0];
            $product_datas = $tmp_token[1];            
            foreach($product_datas["resource"] as $product)
            {
                if(isset($product["variants"]))
                {
                    $sku_count = count($backorder_skus);
                    foreach($product["variants"] as $prod_attr)
                    {
                        // if($prod_attr["inventory_item_id"] == "4261221121")
                        // {
                            // print_r($product["title"]);
                            // print_r("<br />");
                            // print_r($product["id"]);
                            // print_r("<br />");
                            // print_r($prod_attr["product_id"]);
                            // exit();
                        // }
                        for($k = 0; $k < $sku_count; $k ++)
                        {
                            if($backorder_skus[$k] == $prod_attr["sku"])
                            {
                                array_push($total_arr, $prod_attr["inventory_item_id"]);
                            }
                        }
                    }
                }
            }  
        }
        else
        {
            $tmp_token = request_product("GET", "products.json?limit=250&page_info=".$page_info);
            $page_info = $tmp_token[0];
            $product_datas = $tmp_token[1];
            
            foreach($product_datas["resource"] as $product)
            {
                if(isset($product["variants"]))
                {
                    $sku_count = count($backorder_skus);
                    foreach($product["variants"] as $prod_attr)
                    {
                        // if($prod_attr["inventory_item_id"] == "4261221121")
                        // {
                            // print_r($product["title"]);
                            // print_r("<br />");
                            // print_r($product["id"]);
                            // print_r("<br />");
                            // print_r($prod_attr["product_id"]);
                            // exit();
                        // }
                        for($k = 0; $k < $sku_count; $k ++)
                        {
                            if($backorder_skus[$k] == $prod_attr["sku"])
                            {
                                array_push($total_arr, $prod_attr["inventory_item_id"]);
                            }
                        }
                    }
                }
            }
        }
    }

    
    $f_loc_items = [];
    $s_loc_items = [];

    function update_available($loc_id, $arr_inv_item_gid)
    {
        global $request;
        global $sh_api_user;
        global $sh_api_pw;
        global $dev_shop_info;
        global $req_begin;

        $inv_adjust_items = "";
        $location_id ="gid://shopify/Location/".$loc_id;
        $count_gid = count($arr_inv_item_gid);
        for($k = 0; $k < $count_gid; $k++)
        {
            if($k == $count_gid - 1)
            {
                $tmp = ',{inventoryItemId:\\"'.$arr_inv_item_gid[$k].'\\", availableDelta:-1000}';
                $inv_adjust_items .= $tmp;
                $inv_adjust_items .= ']';
                $update_req_body = '{"query":"mutation {inventoryBulkAdjustQuantityAtLocation(locationId:\\"'.$location_id.'\\",inventoryItemAdjustments:'.$inv_adjust_items.'){inventoryLevels{id,available} userErrors{field, message}}}","variables":{}}';
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'https://csa-medical-supply.myshopify.com/admin/api/2021-07/graphql.json',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS =>$update_req_body,
                    CURLOPT_HTTPHEADER => array(
                    'Authorization: Basic MzZjYzkxNTkxNTE2ZmM3NGE3ZTA1YmJlNzA2MGJlY2U6c2hwcGFfMjc3M2QyNTcyNzUwZjc3ZjBmZTg5YTg3YjhjOThiMzg=',
                    'Content-Type: application/json'
                    ),
                ));
                $response = curl_exec($curl);
            
                print_r ($response);
                curl_close($curl);
            }
            if($k % 100 == 0)
            {
                if($k > 0)
                {
                    $inv_adjust_items .= ']';
                    // $update_req_body ='{"query":"mutation {inventoryBulkAdjustQuantityAtLocation(locationId:\\"gid://shopify/Location/15652161\\",inventoryItemAdjustments:[{inventoryItemId:\\"gid://shopify/InventoryItem/754561729\\", availableDelta:-1000},{inventoryItemId:\\"gid://shopify/InventoryItem/2378424833\\", availableDelta:-1000},{inventoryItemId:\\"gid://shopify/InventoryItem/2383816321\\", availableDelta:-1000}]){inventoryLevels{id,available} userErrors{field, message}}}","variables":{}}';
                    $update_req_body = '{"query":"mutation {inventoryBulkAdjustQuantityAtLocation(locationId:\\"'.$location_id.'\\",inventoryItemAdjustments:'.$inv_adjust_items.'){inventoryLevels{id,available} userErrors{field, message}}}","variables":{}}';
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://csa-medical-supply.myshopify.com/admin/api/2021-07/graphql.json',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS =>$update_req_body,
                        CURLOPT_HTTPHEADER => array(
                        'Authorization: Basic MzZjYzkxNTkxNTE2ZmM3NGE3ZTA1YmJlNzA2MGJlY2U6c2hwcGFfMjc3M2QyNTcyNzUwZjc3ZjBmZTg5YTg3YjhjOThiMzg=',
                        'Content-Type: application/json'
                        ),
                    ));
                    $response = curl_exec($curl);
                
                    print_r ($response);
                    curl_close($curl);
                }
                $tmp = '[{inventoryItemId:\\"'.$arr_inv_item_gid[$k].'\\", availableDelta:-1000}';
                $inv_adjust_items = $tmp;
            }
            else{
                $tmp = ',{inventoryItemId:\\"'.$arr_inv_item_gid[$k].'\\", availableDelta:-1000}';
                $inv_adjust_items .= $tmp;
            }
        }
    }

    // ************************ Get Inventory level *********************
    $get_inv_lvls;
    $inv_item_count = count($total_arr);
    if($inv_item_count > 200)
    {
        for($j = 0; $j < $inv_item_count; $j ++)
        {
            
            if($j %200 == 0)
            {
                if($j > 0)
                {
                    $get_inv_url = "/admin/api/2021-07/inventory_levels.json?inventory_item_ids=".$inv_item_ids."&limit=200";
                    $get_inv_lvls = request_data($get_inv_url);
                    foreach($get_inv_lvls->inventory_levels as $get_inv_lvl)
                    {
                        if($get_inv_lvl->location_id == "15652161")
                        {
                            // array_push($f_loc_items, $get_inv_lvl->admin_graphql_api_id);
                            $tmp_item_id_data = "gid://shopify/InventoryItem/". $get_inv_lvl->inventory_item_id;
                            array_push($f_loc_items,$tmp_item_id_data);
                        }
                        else
                        {
                            // array_push($s_loc_items, $get_inv_lvl->admin_graphql_api_id);
                            $tmp_item_id_data = "gid://shopify/InventoryItem/". $get_inv_lvl->inventory_item_id;
                            array_push($s_loc_items,$tmp_item_id_data);
                        }
                    }
                }
                $inv_item_ids = $total_arr[$j];
            }
            else
            {
                $inv_item_ids .= ",".$total_arr[$j]; 
            }
        }
        
    }
    else
    {
        $inv_item_ids = "";
        for($j = 0; $j < $inv_item_count; $j++)
        {
            if($j == 0)
            {
                $inv_item_ids = $total_arr[$j];
            }
            else
            {
                $inv_item_ids .= ",".$total_arr[$j]; 
            }
        }

        $get_inv_url = "/admin/api/2021-07/inventory_levels.json?inventory_item_ids=".$inv_item_ids."&limit=200";
        $get_inv_lvls = request_data($get_inv_url);
        foreach($get_inv_lvls->inventory_levels as $get_inv_lvl)
        {
            if($get_inv_lvl->location_id == "15652161")
            {
                $tmp_item_id_data = "gid://shopify/InventoryItem/". $get_inv_lvl->inventory_item_id;
                array_push($f_loc_items,$tmp_item_id_data);
            }
            else
            {
                $tmp_item_id_data = "gid://shopify/InventoryItem/". $get_inv_lvl->inventory_item_id;
                array_push($s_loc_items,$tmp_item_id_data);
            }
        }

    }
    update_available("15652161", $f_loc_items);
    update_available("32885697", $s_loc_items);

}
    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading Excel file data.</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <h1>Ready to read data</h1>
    <h6>Reading event is happened at 18:00 per day</h6>
    <h3 id="tmp_txt">Checking time...</h3>
    <!-- <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="myform">
        <input name="start_working" hidden />
    </form> -->

<script>
$(document).ready(function(){
    var checked = 0;
    setInterval(() => {
        var dt = new Date();
        var tmp_time = dt.getHours() + ":" + dt.getMinutes() + ":" + dt.getSeconds();
        var time = dt.getHours();
        if(checked == 0)
        {
            if(time == 18)
            {
                $("#tmp_txt").text("Reading data..");
                checked = 1;
                $.post("./index.php",
                {
                    start_working: "Donald Duck"
                },
                function(data, status){
                    if (status == 200)
                    {
                        $("#tmp_txt").text("Checking time...");
                        console.log("successed");
                    }
                });
            }
        }
        if(time == 19)
        {
            checked = 0;
        }
    }, 3600000);
});
</script>
</body>

</html>