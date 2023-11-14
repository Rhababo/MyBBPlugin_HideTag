<?php

$plugins->add_hook("parse_message", "checkForHideTag");

function allowHideTag_info()
{
    return array(
        "name" => "allowHideTag",
        "description" => "Allows users to use a [hide] tag to cause contained content to only display to registered users.",
        "website" => "http://eagle-time.org",
        "author" => "Rhababo",
        "authorsite" => "github.com/Rhababo",
        "version" => "1.0",
        "guid" => "",
        "codename" => "allowHideTag",
        "compatibility" => "18*"
    );
}

function allowHideTag_activate()
{

}

function allowHideTag_deactivate()
{
}

function checkForHideTag($message)
{
    global $mybb;

    if (!isset($mybb->settings['enableHideTag'])) {
        return $message;
    }
    if ($mybb->settings['enableHideTag'] == 0) {
        return $message;
    }

    //check if the message contains a complete [hide] tag
    if (strpos($message, "[hide]") !== false && strpos($message, "[/hide]") !== false){
        //break the message into an array of strings, using the [hide] tag as a captured delimiter
        $messageArray = preg_split('/('.preg_quote('[hide]', '/').'|'.preg_quote('[/hide]', '/').')/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        //foreach element in $messageArray
        //track nesting of Hide Tags,
        //[hide][/hide] pairs nested within other should be ignored and removed
        //if [hide] is missing the [/hide] tag, then don't change $message
        $nestedHideTags = 0;
        $allowedUserGroups = explode(',', $mybb->settings['hideTagUserGroups']);
        $isAllowedUser = in_array($mybb->user['usergroup'], $allowedUserGroups);
        foreach($messageArray as $key => $value){
            //if the element is a [hide] tag
            if($value == "[hide]"){
                //set insideHideTag to true
                if($nestedHideTags == 0){
                    $nestedHideTags = 1;
                    $messageArray[$key] = "<span class='hideTag'>";
                }
                else{
                    $nestedHideTags++;
                    $messageArray[$key] = "";
                }
            }
            elseif($value == "[/hide]"){
                if($nestedHideTags == 1){
                    $nestedHideTags--;
                    $messageArray[$key] = "</span>";
                }
                else if($nestedHideTags > 1){
                    $nestedHideTags--;
                    $messageArray[$key] = "";
                }
                //User has placed a [/hide] tag before a [hide] tag. Return the message as is so they can fix it.
                else{
                    return $message;
                }
            }
            //TODO: update this for selected usergroups in settings

            elseif (! $isAllowedUser&&$nestedHideTags>0) {
                $messageArray[$key] = "<span class='hiddenTagMessage'>" . $mybb->settings['hideTagMessage'] . "</span>";
            }

        }
        //if there aren't the correct number/sequence of hide tags, don't edit the message.
        //NOTE: Maybe there's a better way to check for this before running through the entire message?
        if($nestedHideTags == 0){
            $message = implode($messageArray);
        }
        if($isAllowedUser){
            $message = $message."<hr class='solid'></hr><div class='hiddenTagAlert'>This post contains content only visible to registered users.</div>";
        }
    }

    return $message;

}

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function allowHideTag_install()
{
    global $db, $mybb;

    $setting_group = array(
        'name' => 'allowHideTagSettingGroup',
        'title' => 'Allow Hide Tag',
        'description' => 'Allows users to use a [hide] tag to make contained content hidden to guests.',
        'disporder' => 5,
        'isdefault' => 0
    );

    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = array(

        'enableHideTag' => array(
            'title' => 'Enable this setting',
            'description' => 'Choose to activate/deactive this plugin.',
            'optionscode' => 'onoff',
            'value' => 1,
            'disporder' => 2
        ),
        'hideTagMessage' => array(
            'title' => 'Message to display to guests',
            'description' => 'Choose the message to display to guests when they attempt to view hidden content.',
            'optionscode' => 'textarea',
            'value' => 'You must be logged in to view this content.',
            'disporder' => 3
        ),
        'hideTagUserGroups' => array(
            'title' => 'Usergroups to allow viewing of hidden content',
            'description' => 'Choose the usergroups that can view hidden content.',
            'optionscode' => 'groupselect',
            'value' => '1,2,3,4',
            'disporder' => 4
        )
    );

    foreach ($setting_array as $name => $setting) {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

    rebuild_settings();
}

function allowHideTag_is_installed()
{
    global $mybb;
    if (isset($mybb->settings['enableHideTag'])) {
        return true;
    }

    return false;
}

function allowHideTag_uninstall()
{
    global $db;

    $db->delete_query('settings', "name IN ('enableHideTag')");
    $db->delete_query('settings', "name IN ('hideTagMessage')");
    $db->delete_query('settings', "name IN ('hideTagUserGroups')");
    $db->delete_query('settinggroups', "name = 'allowHideTagSettingGroup'");

    rebuild_settings();
}
