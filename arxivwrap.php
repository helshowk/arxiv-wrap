<?php
    $arguments = array();
    $method = 'json';
    if (isset($_GET['search_query'])) { $arguments[] = "search_query=".str_replace(" ", "+",$_GET['search_query']); }
    if (isset($_GET['id_list'])) { $arguments[] = "id_list=".$_GET['id_list']; }
    if (isset($_GET['start'])) { $arguments[] = "start=".$_GET['start']; } else { $arguments[] = "start=0"; }
    if (isset($_GET['max_results'])) { $arguments[] = "max_results=".$_GET['max_results']; } else {$arguments[] = "max_results=10"; }
    if (isset($_GET['method'])) { $method = $_GET['method']; }
    
    $base_url = 'http://export.arxiv.org/api/query?';
    $arguments_str = implode("&", $arguments);
    $url = $base_url.$arguments_str;
    
    
    $response = file_get_contents($url);
    $response = str_replace("xmlns=", "ns=", $response);
    
    function append_field($field, &$append_array, $level) {
         $field_value = '';
        if ($field->__toString() != '') {
            $field_value = $field->__toString();
        }
        
        $attributes = array();
        foreach($field->attributes() as $attrib) {
            $attributes[$attrib->getName()] = $attrib->__toString();
        }
        if (($field->getName() == 'link') || ($field->getName() == 'category') || ($field->getName() == 'name')) {
            $push = array();
            if ($level > 0) {
                $push[$field->getName()] = array();
                $push[$field->getName()]['value'] = $field_value;
                $push[$field->getName()]['attribs'] = $attributes;
            } else {
                $push['value'] = $field_value;
                $push['attribs'] = $attributes;
            }
            $append_array[] = $push;
        } else {
            $append_array['value'] = $field_value;
            $append_array['attribs'] = $attributes;
        }
    }
    
    if ($method == 'xml') {
        echo htmlentities($response);
    } else {
        
        try {
            $xml = new SimpleXMLElement($response);
            $xml_array = array();
            $entries = array();
            
            foreach ($xml->entry as $entry) {
                $entry_data = array();
                foreach($entry->children() as $field) {
                    if (!isset($entry_data[$field->getName()])) {
                        $entry_data[$field->getName()] = array();
                    }
                    
                    if ($field->count() == 0) {
                       append_field($field, $entry_data[$field->getName()], 0);
                    } else {
                        // only go down one level
                        if (!isset($entry_data[$field->getName()]['value'])) {
                            $entry_data[$field->getName()]['value'] = array();
                        }
                        
                        foreach($field->children() as $sub_field) {
                            //// ignore sub attributes? check arXiv specs
                            //if (!isset($entry_data[$field->getName()]['value'][$sub_field->getName()])) {
                                //$entry_data[$field->getName()]['value'][$sub_field->getName()] = array();
                            //}
                            //$entry_data[$field->getName()]['value'][$sub_field->getName()][] = $sub_field->__toString();
                            append_field($sub_field, $entry_data[$field->getName()]['value'], 1);
                        }
                    }
                }
                $entries[] = $entry_data;
            }
            
            $xml_array['entries_count'] = count($entries);

            if ($xml->link) {
                $link_data = array();
                foreach($xml->link->attributes() as $link_field) {
                    $link_data[$link_field->getName()] = $link_field->__toString();
                }
                $xml_array['link'] = $link_data;
            }
            if ($xml->title) {
                $title_data = array();
                $title_data['value'] = $xml->title->__toString();
                $title_data['type'] = $xml->title['type']->__toString();
                $xml_array['title'] = $title_data;
            }
            if ($xml->id) {
                $xml_array['id'] = array();
                $xml_array['id']['value'] = $xml->id->__toString();;
            }
            if ($xml->updated) {
                $xml_array['updated'] = array();
                $xml_array['updated']['value'] = $xml->updated->__toString();;
            }
            $xml_array['entries'] = $entries;
            echo json_encode($xml_array);
        } catch (Exception $e) {
            echo '';
        }
    }
?>
