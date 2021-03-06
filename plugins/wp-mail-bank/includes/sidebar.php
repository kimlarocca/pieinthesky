<?php
/**
* This file is used for displaying sidebar menus.
*
* @author  Tech Banker
* @package wp-mail-bank/includes
* @version 2.0.0
*/
if(!defined("ABSPATH")) exit;// Exit if accessed directly
if(!is_user_logged_in())
{
	return;
}
else
{
	foreach($user_role_permission as $permission)
	{
		if(current_user_can($permission))
		{
			$access_granted = true;
			break;
		}
	}
	if(!$access_granted)
	{
		return;
	}
	else
	{
		?>
		<div class="page-sidebar-wrapper-tech-banker">
			<div class="page-sidebar-tech-banker navbar-collapse collapse">
				<div class="sidebar-menu-tech-banker">
					<ul class="page-sidebar-menu-tech-banker" data-slide-speed="200">
						<div class="sidebar-search-wrapper" style="padding:20px;text-align:center">
							<a class="plugin-logo" href="<?php echo tech_banker_beta_url; ?>" target="_blank">
								<img src="<?php echo plugins_url("assets/global/img/mail-bank-logo.png",dirname(__FILE__));?>">
							</a>
						</div>
						<li id="ux_mb_li_email_configuration">
							<a href="admin.php?page=mb_email_configuration">
								<i class="icon-custom-envelope-open"></i>
								<span class="title">
									<?php echo $mb_email_configuration; ?>
								</span>
							</a>
						</li>
						<li id="ux_mb_li_test_email">
							<a href="admin.php?page=mb_test_email">
								<i class="icon-custom-envelope "></i>
								<span class="title">
									<?php echo $mb_test_email; ?>
								</span>
							</a>
						</li>
						<li id="ux_mb_li_email_logs">
							<a href="admin.php?page=mb_email_logs">
								<i class="icon-custom-note"></i>
								<span class="title">
									<?php echo $mb_email_logs; ?>
								</span>
							</a>
						</li>
						<li id="ux_mb_li_settings">
							<a href="admin.php?page=mb_settings">
								<i class="icon-custom-paper-clip"></i>
								<span class="title">
									<?php echo $mb_settings; ?>
								</span>
							</a>
						</li>
						<li id="ux_mb_li_roles_and_capabilities">
							<a href="admin.php?page=mb_roles_and_capabilities">
								<i class="icon-custom-user"></i>
								<span class="title">
									<?php echo $mb_roles_and_capabilities; ?>
								</span>
							</a>
						</li>
						<li id="ux_mb_li_feedbacks">
							<a href="admin.php?page=mb_feedbacks">
								<i class="icon-custom-star"></i>
								<span class="title">
									<?php echo $mb_feedbacks; ?>
								</span>
							</a>
						</li>
						<li id="ux_mb_li_system_information">
							<a href="admin.php?page=mb_system_information">
								<i class="icon-custom-screen-desktop"></i>
								<span class="title">
									<?php echo $mb_system_information; ?>
								</span>
							</a>
						</li>
						<li class="" id="ux_li_premium_editions">
							<a href="admin.php?page=mb_premium_editions">
								<i class="icon-custom-key"></i>
								<span class="title">
									<?php echo $mb_premium_editions_label; ?>
								</span>
							</a>
						</li>
					</ul>
				</div>
			</div>
		</div>
	<?php
	}
}
?>
