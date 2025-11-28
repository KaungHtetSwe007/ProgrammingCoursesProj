<?php
require __DIR__ . '/includes/functions.php';

$_SESSION = [];
session_regenerate_id(true);
set_flash('success', 'အကောင့်မှ ထွက်ခွာပြီးပါပြီ။');
redirect('index.php');
