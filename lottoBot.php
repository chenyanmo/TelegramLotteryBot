<?php
/**
 * Metal.Place Lottery Bot
 */

/** Error reporting */
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
date_default_timezone_set('Europe/Moscow');

require_once __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use zkr\classes\Lotto;
use zkr\classes\TelegramBot;

/*
$data = include __DIR__ . '/data/test_data.php';  // тестовые данные
$response = json_decode($data, true);
*/

$starTime = time();  // время запуска
$timeForMoreSend = $starTime + $intervalSecondSec; // время отправки шансов

$bot = new TelegramBot($token);
$lotto = new Lotto();

//$bot->pinChatMessage($arChatId[0], 17);

while (true) {
    $response = $bot->getUpdatesOffset(30); // получаем последние 30 сообщений
    if ($response && $response["ok"]) {

        $arTickets = $lotto->getTickets() ?: []; // получить выданные билеты
        ksort($arTickets);
        $last = end($arTickets);  // последний элемент
        $ticketNumber = $last ? (key($arTickets) + 1) : 1; // +1 т.к. нумерация с 0, а 0-го номера билета нету
        $ticketCount = count($arTickets);

        $arStoredMembers = $lotto->getMembers() ?: []; // get all stored members
        $storeMemberCount = count($arStoredMembers);

        $arNewMembers = $lotto->getAddedMembers($response["result"], $arChatId); // новые пользователи в чате

        $arProcessData = $lotto->getProcessData() ?: []; // основные данные
        $processDataCount = count($arProcessData);

        foreach ($arNewMembers as $user) {
            // если приглашающий отсутствует - добавляем в массив пользователей
            if (!isset($arStoredMembers[$user["FROM"]["ID"]])) {
                $arStoredMembers[$user["FROM"]["ID"]] = $user["FROM"];  // add new member
            }
            // если это новый приглашённый пользовватель - добавляем, записываем данные и выдаём билет
            foreach ($user["NEW_MEMBER"] as $newMember) {
                if (!isset($arStoredMembers[$newMember["ID"]])) {
                    $arStoredMembers[$newMember["ID"]] = $newMember; // add new member

                    $key = $user["UPDATE_ID"] . '_' . $user["DATE"] . "_" . $user["FROM"]["ID"] . "_" . $newMember["ID"];
                    $arProcessData[$key] = [
                        "UPDATE_ID"  => $user["UPDATE_ID"],
                        "DATE"       => $user["DATE"],
                        "USER_ID"    => $user["FROM"]["ID"],
                        "TICKET_ID"  => 0,
                        "NEW_MEMBER" => $newMember["ID"]
                    ];

                    $numberInvitedMembers = $lotto->getNumberInvitedMembers($user["FROM"]["ID"], $arProcessData);
                    // если приглашённых больше опредёлённого количества
                    if (($numberInvitedMembers % 2) == 0) { // чётное количество приглашённых - выдаём билет
                        // добавляем новый номер билетика и запоминаем, кому выдали
                        $arTickets[$ticketNumber] = $user["FROM"]["ID"];

                        $arProcessData[$key]["TICKET_ID"] = $ticketNumber;

                        // 1-й приглашённый по билету
                        $firstInvitedMember = $lotto->getFirstInvitedMember($user["FROM"]["ID"], $arProcessData);
                        // присваиваем ему номер билета
                        $firstInvitedMember["TICKET_ID"] = $ticketNumber;

                        // запоминаем данные
                        $firstInvitedMemberKey = $firstInvitedMember["UPDATE_ID"] . '_' . $firstInvitedMember["DATE"] . "_" . $firstInvitedMember["USER_ID"] . "_" . $firstInvitedMember["NEW_MEMBER"];
                        $arProcessData[$firstInvitedMemberKey] = $firstInvitedMember;
                        $msg = $user["FROM"]["FIRST_NAME"] . " " . $user["FROM"]["LAST_NAME"]
//                            . " " . $user["FROM"]["USERNAME"]
                            . " (" . $user["FROM"]["ID"] . ")"
                            . " - присвоен билет № " . $ticketNumber . PHP_EOL
                            . "за приглашение "
                            . $arStoredMembers[$firstInvitedMember["NEW_MEMBER"]]["FIRST_NAME"]
                            . " " . $arStoredMembers[$firstInvitedMember["NEW_MEMBER"]]["LAST_NAME"]
//                            . " " . $arStoredMembers[$firstInvitedMember["NEW_MEMBER"]]["USERNAME"]
                            . " (" . $arStoredMembers[$firstInvitedMember["NEW_MEMBER"]]["ID"] . ")"
                            . " и " . $newMember["FIRST_NAME"] . " " . $newMember["LAST_NAME"]
//                            . " " . $newMember["USERNAME"]
                            . " (" . $newMember["ID"] . ")"
                            . PHP_EOL . "[Правила конкурса]({$urlRules})";
//                            . PHP_EOL . "[Текущие результаты]({$urlXlsx})";

                        $bot->sendMessageToChats($arChatId, $msg, "markdown", true, true);

                        $ticketNumber++;
                    }
                }
            }
        }

        $isChange = false;
        if ($storeMemberCount != count($arStoredMembers)) { // если были новые пользователи
            $lotto->saveData($arStoredMembers, "members"); // сохранить новых пользователей
            $isChange = true;
        }
        if ($ticketCount != count($arTickets)) {
            $lotto->saveData($arTickets, "tickets"); // сохранить билеты
            $isChange = true;
        }
        if ($processDataCount != count($arProcessData)) {
            $lotto->saveData($arProcessData, "process"); // сохранить данные
            $isChange = true;
        }

        if (time() >= $timeForMoreSend) {
            $chanceText = $lotto->getChanceOfWinning($arTickets, $arStoredMembers, $memberCnt);
            $bot->sendMessageToChats($arChatId, $chanceText, "markdown", true, true);
            $timeForMoreSend = time() + $intervalSecondSec;
        }

        if ($isChange) {
            // Create new PHPExcel object
            $objPHPExcel = new PHPExcel();

            $lotto->reportToExcel($objPHPExcel, $arProcessData, $arStoredMembers);

            // Save Excel 2007 file
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            $objWriter->save('data/results.xlsx');

            // Create new PHPExcel object
            $objPHPExcelProcess = new PHPExcel();

            $lotto->processToExcel($objPHPExcelProcess, $arProcessData);

            $lotto->membersToExcel($objPHPExcelProcess, $arStoredMembers);

            $lotto->ticketsToExcel($objPHPExcelProcess, $arTickets);

            $objPHPExcel->setActiveSheetIndex(0);

            // Save Excel 2007 file
            $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
            $objWriter->save('data/process.xlsx');
        }
    }

//    exit();

    sleep($intervalSec);
}