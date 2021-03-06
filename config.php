<?php

$listChats = [
    "@test_group_test" => -1001283156137,   // Test group
    "@shownewcoin"     => -1001325237083,   // Test Channel SendNewCoin
    "@buymetal"        => -1001284362557,   // Куплю - Metal.place
    "@metalplacechat"  => -1001085505193,   // Metal.Place chat
    "@test_loto_bot"   => -1001336939248,   // Test lotto bot
];

return [
    'arChatId'     => [$listChats["@test_group_test"],],
    'lastMessages' => 30, // последние 30 сообщений
    'intervalSec'  => 10, // sec как часто сканировать чат
    'urlRules'     => "https://t.me/metalplacechat/2579",  // a link to the message in chat

    'showTop'           => true, // show TOP
    'memberCnt'         => 10,  // сколько первых участников показывать с шансом выигрыша
    'intervalSecondSec' => 60 * 10, // 10 min  (используется, если arSpecificTime пустой)
    'arSpecificTime'    => ["09:00:00", "16:00:00", "19:30:00", "19:35:00",], // конкретное время вывода

    'xmlReports' => false, // create xml-files

    'arMembersStatus' => ["creator", "administrator", "member", "restricted"],

    'channel' => [
        "name"     => "@shownewcoin",  // канал, в котором д.б. зарегистрирован участник
        "id"       => [$listChats["@shownewcoin"]],
        "interval" => 30, // sec
    ],

    //'urlXlsx' => "https://metal.place/lottery/data/results.xlsx",
];