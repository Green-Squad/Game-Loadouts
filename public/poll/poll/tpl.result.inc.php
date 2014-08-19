<?php $poll =& $this->poll; ?><!-- You always need this line on the top -->

<!--
	This demo uses "foreach" to loop through all the poll items.
-->

<div class="poll-result col-md-4" style='display:none;'>
<h2>Poll</h2>
<span class="line"> <span class="sub-line"></span> </span>	
<form>
<input type='hidden' name='msg-thank-you' value='Thank you for voting!' />

<table style="margin:10px auto;width:100%;"
	border="0" cellpadding="2" cellspacing="0">

<tr>
<h3><?php echo $poll->attr( "title" ); ?></h3>
</tr>

<tr>
	<td colspan="4">&nbsp;</td>
</tr>

<?php $poll->setMaxWidth( 80 ); ?>

<!-- [BEGIN] Looping through all the items -->
<?php foreach( $poll->getAllItems() as $item ) { ?>
<tr>
	<td align='left'><?php echo $item->getName(); ?></td>
	<td align='left' width='80'>
		<div class='ap-bar'
			style='display:none;width:<?php echo $item->getWidth(); ?>px;
				height:10px;background-color:<?php echo $item->attr( 'bar-color' ); ?>;'>
		</div>
	</td>
	<td align='right' width='40'><?php echo $item->getPercent(); ?>%</td>
	<td align='right' width='30'><?php echo $item->getCount(); ?></td>
</tr>
<?php } ?>
<!-- [END] Looping through all the items -->

<!-- [BEGIN] Show total vote counts -->
<tr>
	<td align='right' colspan='4'>
	<span style='font-weight:bold;'><?php echo $poll->getTotal(); ?>
		vote<?php if ( $poll->getTotal() != 1 ) echo "s"; ?> total</span>
	</td>
</tr>
<!-- [END] Show total vote counts -->

<!-- [BEGIN] Back button -->
<tr>
	<td align='center' colspan='4'>
	<input value=" Back " type="button" class="ap-front" />
	</td>
</tr>
<!-- [END] Back button -->

</table>

</form>

</div>
