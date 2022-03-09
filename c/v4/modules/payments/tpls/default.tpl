<?php

$tpl = array();

$tpl['default'] = '
	html
';


$tpl['stuckHold'] = '
<header>
	<div class="wrap">
		<h1>Зависшие холды</h1>
		<div class="path">
			<a href="/">111</a>
			<a href="/v4/payments/">2222</a>
			<a href="/v4/payments/stuckHold/">Зависшие холды</a></div>
</header>
%menu%
<main id="stuckHold">
	<section id="result" class="block">
		<div class="title">
			<div class="n">Результат:</div>
			<div class="stat"></div>
		</div>
		<div class="data">...</div>
		<div class="pager">...</div>
	</section>
</main>
';
