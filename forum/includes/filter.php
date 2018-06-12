<?php
/*
 * JohnCMS NEXT Mobile Content Management System (http://johncms.com)
 *
 * For copyright and license information, please see the LICENSE.md
 * Installing the system or redistributions of files must retain the above copyright notice.
 *
 * @link        http://johncms.com JohnCMS Project
 * @copyright   Copyright (C) JohnCMS Community
 * @license     GPL-3
 */

defined('_IN_JOHNCMS') or die('Error: restricted access');

require('../system/head.php');

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var Johncms\Api\ToolsInterface $tools */
$tools = $container->get(Johncms\Api\ToolsInterface::class);

if (!$id) {
    echo $tools->displayError(_t('Wrong data'), '<a href="/forum/index.html">' . _t('Forum') . '</a>');
    require('../system/end.php');
    exit;
}
$r_check = $db->query("SELECT * FROM `forum` WHERE `id` = '$id'")->fetch();
if ($r_check['type'] != 't') {
    echo $tools->displayError(_t('Wrong data'));
    require('../system/end.php');
    exit;
}
switch ($do) {
    case 'unset':
        // Удаляем фильтр
        unset($_SESSION['fsort_id']);
        unset($_SESSION['fsort_users']);
        header("Location: /forum/$id/$r_check[seo].html");
        break;

    case 'set':
        // Устанавливаем фильтр по авторам
        $users = isset($_POST['users']) ? $_POST['users'] : '';

        if (empty($_POST['users'])) {
            echo '<div class="rmenu"><p>' . _t('You have not selected any author') . '<br /><a href="index.php?act=filter&amp;id=' . $id . '&amp;start=' . $start . '">' . _t('Back') . '</a></p></div>';
            require('../system/end.php');
            exit;
        }

        $array = [];

        foreach ($users as $val) {
            $array[] = intval($val);
        }

        $_SESSION['fsort_id'] = $id;
        $_SESSION['fsort_users'] = serialize($array);
        header("Location: /forum/$id/$r_check[seo].html");
        break;

    default :
        /** @var PDO $db */
        $db = $container->get(PDO::class);

        // Показываем список авторов темы, с возможностью выбора
        $req = $db->query("SELECT *, COUNT(`from`) AS `count` FROM `forum` WHERE `refid` = '$id' GROUP BY `from` ORDER BY `from`");
        $total = $req->rowCount();
        echo '<div class="mrt-code card shadow--2dp">';
        if ($total) {
            echo '<div class="phdr"><a href="/forum/' . $id . '/' . $r_check['seo'] . '_start' . $start . '.html"><b>' . _t('Forum') . '</b></a>&#160;|&#160;' . _t('Filter by author') . '</div>' .
                '<form action="index.php?act=filter&amp;id=' . $id . '&amp;start=' . $start . '&amp;do=set" method="post">';
            $i = 0;
            echo '<div class="list1">';
            while ($res = $req->fetch()) {
                echo '<div class="list3">';
                echo '<div class="checkbox"><label><input type="checkbox" name="users[]" value="' . $res['user_id'] . '"/><i class="helper"></i></label></div>&#160;' .
                    '<a href="../profile/?user=' . $res['user_id'] . '">' . $res['from'] . '</a> [' . $res['count'] . ']</div>';
                ++$i;
            }
            echo '<div class="button-container"><button class="button" type="submit" name="submit"><span>' . _t('Filter') . '</span></button></div>';
            echo '</div>' .
                '<div class="gmenu"><small>' . _t('Filter will be display posts from selected authors only') . '</small></div>' .
                '</form>';
        } else {
            echo $tools->displayError(_t('Wrong data'));
        }
        echo '</div>';
}
echo '<div class="mrt-code card shadow--2dp"><div class="list1"><a href="/forum/' . $id . '/' . $r_check['seo'] . '_start' . $start . '.html">' . _t('Back to topic') . '</a></div></div>';
