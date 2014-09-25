<?php $poll=& $this->poll; ?>
<!-- You always need this line on the top -->

<!--
	This demo uses "foreach" to loop through all the poll items.
-->

<div class="poll-result col-md-4" style='display:none;'>
	<h2>Poll</h2>
	<span class="line"> <span class="sub-line"></span> </span>
	<form>
		<div class="row">
			<div class="col-md-12">
				<h3><?php echo $poll->attr( "title" ); ?></h3>

				<?php $poll->setMaxWidth( 100 ); ?>

				<!-- [BEGIN] Looping through all the items -->
				<?php foreach( $poll->getAllItems() as $item ) { ?>
				<div class="row">
					<div class="col-xs-12">
						<?php echo $item->getName(); ?>
						<div class="pull-right">
							<?php echo $item->getCount(); ?> Votes
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-xs-12">
						<div class="progress" style="border-radius:4px;">
							<div class="progress-bar" role="progressbar" aria-valuenow="<?php echo $item->getPercent(); ?>" aria-valuemin="0" aria-valuemax="100" style="min-width:20px;line-height: 20px;text-align:left;border-radius:4px;text-indent:5px;height:20px;width: <?php echo $item->getPercent(); ?>%;">
								<?php echo $item->getPercent(); ?>%
							</div>
						</div>
					</div>
				</div>
				<?php } ?>
				<!-- [END] Looping through all the items -->

				<!-- [BEGIN] Back button -->
				<div class="row">
					<div class="col-xs-12">
						<input value=" Back " type="button" class="ap-front btn btn-default" />
					</div>
				</div>
				<!-- [END] Back button -->

			</div>
		</div>


	</form>

</div>
