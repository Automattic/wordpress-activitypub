<?php
/**
 * ActivityPub E-Mail template header.
 *
 * @package Activitypub
 */

?>
<style>
	body {
		font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
		font-size: 16px;
		line-height: 1.5;
		color: #222;
		background-color: #ffffff;
		margin: 0;
		padding: 0;
	}
	@media only screen and (max-width: 599px) {
		body {
			background-color: #f9f9f9 !important;
		}
	}
	.container {
		max-width: 600px;
		margin: 20px auto;
		padding: 20px;
		background-color: #f9f9f9;
		border-radius: 8px;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
	}
	@media only screen and (max-width: 599px) {
		.container {
			border-radius: 0 !important;
			box-shadow: none !important;
			margin: 0 !important;
		}
	}
	h1 {
		font-size: 20px;
		margin-bottom: 16px;
	}
	a.button {
		display: inline-block;
		background-color: #2271b1;
		color: #ffffff !important;
		text-decoration: none;
		padding: 10px 16px;
		border-radius: 4px;
		font-weight: bold;
		margin: 10px 0;
	}
	.footer {
		font-size: 13px;
		color: #777;
		margin-top: 30px;
	}
</style>

<div class="container">
