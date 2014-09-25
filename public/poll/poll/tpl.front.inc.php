<?php $poll=& $this->poll; ?>
<!-- You always need this line on the top -->

<!--
	This demo uses "foreach" to loop through all the poll items.
-->

<div class="poll-front col-md-4">
	<h2>Poll</h2>
	<span class="line"> <span class="sub-line"></span> </span>
	<form>
		<div class="row">
			<div class="col-md-12">
				<h3><?php echo $poll->attr( "title" ); ?></h3>

				<!-- [BEGIN] Looping through all the items -->
				<?php foreach( $poll->getAllItems() as $item ) { ?>
				<div class="radio" style="margin:15px 0;">
					<input type="radio" name="answer" value="<?php echo $item->getName(); ?>" />
					<?php echo $item->getName(); ?>
				</div>
				<?php } ?>
				<!-- [END] Looping through all the items -->

				<!-- [BEGIN] Vote & View Buttons -->
				<div class="form-group">
					<input type="submit" class="ap-vote btn btn-primary" value=" Vote " />
					<input type="submit" class="ap-result btn btn-default" value=" View " />
				</div>
				<!-- [END] Vote & View Buttons -->

			</div>
		</div>

		<!-- [BEGIN] Reset Button -->
		<div style='text-align:center;padding:0 10px 10px 10px;'>
			<input type="submit" class="ap-clear-block" value="    Clear IP & Cookie Blocks   " />
		</div>
		<!-- [END] Reset Button -->

	</form>

</div>
