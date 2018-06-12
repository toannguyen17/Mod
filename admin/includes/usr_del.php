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

defined('_IN_JOHNADM') or die('Error: restricted access');

/** @var Psr\Container\ContainerInterface $container */
$container = App::getContainer();

/** @var PDO $db */
$db = $container->get(PDO::class);

/** @var Johncms\Api\UserInterface $systemUser */
$systemUser = $container->get(Johncms\Api\UserInterface::class);

/** @var Johncms\Api\ToolsInterface $tools */
$tools = $container->get(Johncms\Api\ToolsInterface::class);

// Проверяем права доступа
if ($systemUser->rights < 9) {
    header('Location: /?err');
    exit;
}

$mod = isset($_GET['mod']) ? trim($_GET['mod']) : '';

$user = false;
$error = false;

if ($id && $id != $systemUser->id) {
    // Получаем данные юзера
    $req = $db->query('SELECT * FROM `users` WHERE `id` = ' . $id);

    if ($req->rowCount()) {
        $user = $req->fetch();

        if ($user['rights'] > $systemUser->rights) {
            $error = _t('You cannot delete higher administration');
        }
    } else {
        $error = _t('User does not exists');
    }
} else {
    $error = _t('Wrong data');
}

if (!$error) {
    // Считаем комментарии в библиотеке
    $comm_lib = (int)$db->query("SELECT COUNT(*) FROM `cms_library_comments` WHERE `user_id` = '" . $user['id'] . "'")->fetchColumn();

    // Считаем комментарии к загрузкам
    $comm_dl = (int)$db->query("SELECT COUNT(*) FROM `download__comments` WHERE `user_id` = '" . $user['id'] . "'")->fetchColumn();

    // Считаем посты в личных гостевых
    $comm_gb = (int)$db->query("SELECT COUNT(*) FROM `cms_users_guestbook` WHERE `user_id` = '" . $user['id'] . "'")->fetchColumn();

    // Считаем комментарии в личных альбомах
    $comm_al = (int)$db->query("SELECT COUNT(*) FROM `cms_album_comments` WHERE `user_id` = '" . $user['id'] . "'")->fetchColumn();
    $comm_count = $comm_lib + $comm_dl + $comm_gb + $comm_al;

    // Считаем посты в Гостевой
    $guest_count = $db->query("SELECT COUNT(*) FROM `guest` WHERE `user_id` = '" . $user['id'] . "'")->fetchColumn();

    // Считаем созданные темы на Форуме
    $forumt_count = $db->query("SELECT COUNT(*) FROM `forum` WHERE `user_id` = '" . $user['id'] . "' AND `type` = 't' AND `close` != '1'")->fetchColumn();

    // Считаем посты на Форуме
    $forump_count = $db->query("SELECT COUNT(*) FROM `forum` WHERE `user_id` = '" . $user['id'] . "' AND `type` = 'm'  AND `close` != '1'")->fetchColumn();

    echo '<div class="mrt-code card shadow--2dp"><div class="phdr"><h4><a href="index.php"><b>' . _t('Admin Panel') . '</b></a>&#160;|&#160;' . _t('Delete user') . '</h4></div>';

    // Выводим краткие данные
    echo '<div class="user"><p>' . $tools->displayUser($user, [
            'lastvisit' => 1,
            'iphist'    => 1,
        ]) . '</p></div>';

    switch ($mod) {

        case 'del':
            // Удаляем личные данные
            $del = new Johncms\CleanUser;
            $del->removeAlbum($user['id']);         // Удаляем личные Фотоальбомы
            $del->removeGuestbook($user['id']);     // Удаляем личную Гостевую
            $del->removeMail($user['id']);          // Удаляем почту
            $del->removeKarma($user['id']);         // Удаляем карму

            if (isset($_POST['comments'])) {
                $del->cleanComments($user['id']);   // Удаляем комментарии
            }

            if (isset($_POST['forum'])) {
                $del->cleanForum($user['id']);      // Чистим Форум
            }
            if($user['avatar_extension'] != 'none'){
                $photo_dir = '../files/users/avatar';
                $ext_base = $user['avatar_extension'];
                if (file_exists(($photo_dir . '/' . $user['id'] . '.' . $ext_base))) {
                    @unlink($photo_dir . '/' . $user['id'] . '.' . $ext_base);
                }
                if (file_exists(($photo_dir . '/' . $user['id'] . '_100x100.' . $ext_base))) {
                    @unlink($photo_dir . '/' . $user['id'] . '_100x100.' . $ext_base);
                }
                if (file_exists(($photo_dir . '/' . $user['id'] . '_100x75.' . $ext_base))) {
                    @unlink($photo_dir . '/' . $user['id'] . '_100x75.' . $ext_base);
                }
                if (file_exists(($photo_dir . '/' . $user['id'] . '_thumb.' . $ext_base))) {
                    @unlink($photo_dir . '/' . $user['id'] . '_thumb.' . $ext_base);
                }
            }
            
            $del->removeUser($user['id']);          // Удаляем пользователя

            // Оптимизируем таблицы
            $db->query("
                OPTIMIZE TABLE
                `cms_users_iphistory`,
                `cms_ban_users`,
                `guest`,
                `cms_album_comments`,
                `cms_users_guestbook`,
                `karma_users`,
                `cms_album_votes`,
                `cms_album_views`,
                `cms_album_downloads`,
                `cms_album_cat`,
                `cms_album_files`,
                `cms_forum_rdm`
            ");

            echo '<div class="rmenu text-center">' . _t('User deleted') . '</div>';
            break;

        default:
            // Форма параметров удаления
            echo '<form action="index.php?act=usr_del&amp;mod=del&amp;id=' . $user['id'] . '" method="post"><div class="menu"><p><h3>' . _t('Cleaning activities') . '</h3>';

            if ($comm_count) {
                echo '<div class="checkbox"><label><input type="checkbox" value="1" name="comments" checked="checked" /><i class="helper"></i>&#160;' . _t('Comments') . ' </label></div><span class="red">(' . $comm_count . ')</span><br />';
            }

            if ($forumt_count || $forump_count) {
                echo '<div class="checkbox"><label><input type="checkbox" value="1" name="forum" checked="checked" /><i class="helper"></i>&#160;' . _t('Forum') . ' </label></div><span class="red">(' . $forumt_count . '&nbsp;/&nbsp;' . $forump_count . ')</span>';
                echo '<br /><small><span class="gray">' . _t('All threads and posts created by the user go in the hidden state') . '</span></small>';
            }

            echo '</p></div><div class="rmenu text-center">' . _t('Are you sure that you want to delete this user?');
            echo '<div class="button-container"><button  type="submit" name="submit" class="button"><span>' . _t('Delete') . '</span></button></div>';
            echo '</div></form>';
    }
    echo '</div>';
} else {
    echo $tools->displayError($error);
}

