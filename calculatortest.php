
<?php
// @author Arpitha Rajanna


class Paydate_Calculator
{	
	private $one_day = 86400; //unix timestamp for 01/02/1970 @ 12:00am (UTC)
	
	/**
	 * 		This function determines the first available due date following the funding of a loan.
	 *   The paydate will be at least 10 days in the future from the $fund_day.  The 
	 *   due_date will fall on a day that is a paydate based on their paydate model
	 *   specified by '$pay_span' unless the date must be adjusted forward to miss a weekend or backward to miss a holiday.
	 *   Holiday adjustment takes precedence over Weekend.
	 * 
	 *   @param unix_timestamp $fund_day  The day the loan was funded.
	 *   @param array $holiday_array  An array of unix timestamp's containing holidays.
	 *   @param string $pay_span  A string representing the frequency at which the customer is paid.  (weekly,bi-weekly,monthly)
	 *   @param unix_timestamp $pay_day  A timestamp containing one of the customers paydays
	 *   @param bool $direct_deposit  A boolean determining whether or not the customer receives their paycheck via direct deposit
	 *   @return unix_timestamp  A unix timestamp representing the determineed due date
	 * 
	 *   Assumptions:  All timestamps are at 12:00
	 */ 	
	public function Calculate_Due_Date($fund_day, $holiday_array, $pay_span, $pay_day, $direct_deposit)
	{
		$due_date = 0;
		
		// Minimum payday must be at least 10 days from the fund day
		$minimum_payday = $fund_day + (10*$this->one_day);
		 
		$pay_date_iteration = 0;  // start with our first available pay date (first iteration of all upcoming pay dates)
		
		// loop until we have satisfied our 10 day minimum
		while ($due_date < $minimum_payday) { 
			$due_date = $this->nextPayday($fund_day, $pay_span, $pay_day,$pay_date_iteration);
		
			if(!$direct_deposit) {
				$due_date += $this->one_day;
			} 
			
			// process all weekends and holidays, return due_date that does not fall on any of these
			$due_date = $this->processNonPaydays($due_date, $holiday_array);
			
			// while due_date not valid ( > minimum_payday ), try next pay_date iteration
			$pay_date_iteration += 1;
		}

		// Final Due Date = 1st $pay_day after $fund_day	
		return $due_date;
	}
	
	/**
	 *  Calculate next possible payday after the fund_day according to our pay_span
	 * 			
	 * @param unix_timestamp $fund_day The day the loan was funded
	 * @param string $pay_span  A string representing the frequency at which the customer is paid.  (weekly,bi-weekly,monthly)
	 * @param unix_timestamp $pay_day  A timestamp containing one of the customers paydays
	 * @param int $pay_date_iteration Number of pay dates to skip
	 * @param unix_timestamp $pay_day  A timestamp containing one of the customers paydays
	 * 
	 * Assumptions:  
	 * 		Monthly paydays are on a fixed date (1st, 30th, etc).  
	 * 		Weekly days are fixed to days of the week (monday, tues; every 7 days)
	 * 		Bi-weekly is are fixed to days of the week (every 2 weeks; 14 days)
	 * 
	 */
	private function nextPayday($fund_day, $pay_span, $pay_day, $pay_date_iteration)
	{
		// middle of a payperiod (first pay date iteration)
		if($pay_day > $fund_day && $pay_date_iteration == 0) {
			$pay_frequency = 0;
		}
		// all paychecks after the first payperiod
		else {
			switch ($pay_span) {
				case "weekly":
					$pay_frequency = 7;				
					break;
				case "bi-weekly":
					$pay_frequency = 14;
					break;
				case "monthly":
					$pay_frequency = date('t', $fund_day); // # of days in funding month
					break;		
				default:
					$pay_frequency = 30;				
					break;
			}
		}
		
		$pay_frequency *= $pay_date_iteration;			// jump to x iteration of pay dates
		
		$int_fund_day	= date("j",$fund_day); 			// int representing day of month for fund_day
		$int_pay_day	= date("j",$pay_day);  			// int representing day of month for pay_day
		$offset			= $int_fund_day - $int_pay_day; // difference between fund_day and pay_day
		$remaining_days = $pay_frequency - $offset;		// days left til payday
		
		// next pay day is fund_day + days til payday 
		$next_pay_day 	= $fund_day + ($remaining_days*$this->one_day);
		
		return $next_pay_day;
	}
	
	/**
	 * Process weekends and holiday
	 *
	 * @param unix_timestamp $due_date
	 * @param array $holiday_array  An array of unix timestamp's containing holidays.
	 */
	private function processNonPaydays($due_date, $holiday_array)
	{		
		// check for holiday
		if($this->isHoliday($due_date, $holiday_array)) {
		
			// special case (if holiday is Monday, shift payday to previous Friday; minus 3 days)
			if(date("N",$due_date) == 1) {
				$due_date -= (3*$this->one_day);
			}
			// all other holiday days, shift payday back by one day
			else {
				$due_date -= $this->one_day;
			}
			// reprocess with adjusted due_date
			return $this->processNonPaydays($due_date, $holiday_array);
		} 
		else {
 			// if weekend day, add one day, reprocess with adjusted due_date
			if($this->isWeekend($due_date)) {	
				$due_date += $this->one_day;				
				return $this->processNonPaydays($due_date, $holiday_array);
			}	
			else {
				return $due_date;
			}
		}		
	}
	
	/**
	 *  Checks due_date for possibility of being a weekend day
	 * 
	 *	@param unix_timestamp $due_date  Day to check for weekend 
	 *  @return bool true/false based on whether $due_date is a weekend day
	 */
	private function isWeekend($due_date)
	{
		// If $due_date is a Sat or Sun, true
		if (date("N",$due_date) == 6 || date("N",$due_date) == 7)
			return true;
		else 
			return false;
	}
	
	/**
	 * Checks due_date against holiday_array for possibility of falling on a holiday
	 * 
	 * @param unix_timestamp $due_date
	 * @return bool true/false based on whether $due_date is a holiday
	 */
	private function isHoliday($due_date, $holiday_array)
	{
		return in_array($due_date, $holiday_array);	
	}
}

	
	/**
	 * 
	 * 		Holiday										Timestamp
	 *	Monday, January 1	New Year’s Day     			|1514764800
		Monday, February 19*	Washington’s Birthday   |1515974400
		Monday, May 28	Memorial Day 					|1518998400
		Wednesday, July 4	Independence Day   			|1527465600
		Monday, September 3	Labor Day 					|1530662400
		Monday, October 8	Columbus Day 				|1535932800
		Monday, November 12**	Veterans Day    		|1541980800
		Thursday, November 22	Thanksgiving Day  		|1542844800
		Tuesday, December 25	Christmas Day			|1545696000
	 * 	
	 * All timestamps are as 12:00.  ex.  TIME STAMP: 1545696000 DATE: 12 / 25 / 2018 @ 12:00
	 */
	$holiday_array 	= array(1514764800, 1515974400, 1518998400,1527465600, 1530662400, 1535932800, 1538956800,1541980800, 1542844800, 1545696000);


	$Paydate_Calculator = new Paydate_Calculator();
	
?>
            
<!-- 

	I have taken few examples to test the code with different dates and scenarios

-->

<html>
	<head>
		<title> Paydate Calculator</title>
	</head>
	<body>
	
		<div style="float:left;clear:both;">
			----------------WEEKLY TESTING-------------------------
			
			<!-- ex 1 -->
			<?php		
/*    ------------------------------------------------WEEKLY TESTING ----------------------------------------------     */
/*    -------------------------------------------------------------------------------------------------------------     */
				//----------------------------
				$fund_day  	= 1525910400;	// May 10, 2018  	
				$pay_day	= 1525651200;	// May 7, 2018  	
				$due_date = $Paydate_Calculator->Calculate_Due_Date($fund_day,$holiday_array,"weekly",$pay_day,false);		
				//
				// weekly
				// Answer: May 22, 2018				// ---------------------------
			?>
			<ul style="float:left">
				<li>Fund Day: <?php echo date("D F j, Y",$fund_day); ?></li>
				<li>Pay Day: <?php echo date("D F j, Y",$pay_day); ?></li>
				<li>Frequency:  Weekly</li>
				<li><b>Due Date: <?php echo date("D F j, Y",$due_date); ?></b></li>
			</ul>
			
			<!-- ex 2 -->
			<?php
				//----------------------------
				$fund_day	= 1536105600;	// sep 5, 2018 
				$pay_day 	= 1535328000;	// Aug 27, 2018
				$due_date = $Paydate_Calculator->Calculate_Due_Date($fund_day,$holiday_array,"weekly",$pay_day,false);
				//
				// weekly
				// Answer: September 28, 2018
				// ---------------------------
			?>
			<ul style="float:left">
				<li>Fund Day: <?php echo date("D F j, Y",$fund_day); ?></li>
				<li>Pay Day: <?php echo date("D F j, Y",$pay_day); ?></li>
				<li>Frequency:  Weekly</li>
				<li><b>Due Date: <?php echo date("D F j, Y",$due_date); ?></b></li>
			</ul>
			
			
		
		
		<div style="float:left;clear:both;">
			-------------------BI-WEEKLY TESTING-------------------
			
			<!-- ex 1 -->
			<?php
/*    ------------------------------------------------BI-WEEKLY TESTING ----------------------------------------------     */
/*    ----------------------------------------------------------------------------------------------------------------     */
				//----------------------------
				$fund_day	= 1527724800;	// May  31, 2018  
				$pay_day 	= 1528675200;	// June 11, 2018 	
				$due_date = $Paydate_Calculator->Calculate_Due_Date($fund_day,$holiday_array,"bi-weekly",$pay_day,true);
				//
				// bi-weekly
				// Answer: June 22, 2018
				// ---------------------------
			?>
		
			<ul style="float:left">
				<li>Fund Day: <?php echo date("D F j, Y",$fund_day); ?></li>
				<li>Pay Day: <?php echo date("D F j, Y",$pay_day); ?></li>
				<li>Frequency:  Bi-Weekly</li>
				<li><b>Due Date: <?php echo date("D F j, Y",$due_date); ?></b></li>
			</ul>
			
			<!-- ex 2 -->			
			<?php
			
				//----------------------------
				$fund_day	= 1528675200;	// June 11, 2018  	
				$pay_day 	= 1528070400;	// June 4, 2018 
				$due_date = $Paydate_Calculator->Calculate_Due_Date($fund_day,$holiday_array,"bi-weekly",$pay_day,true);
				//
				// bi-weekly
				// Answer: July 2, 2018
				// ---------------------------
			?>
			
			<ul style="float:left">
				<li>Fund Day: <?php echo date("D F j, Y",$fund_day); ?></li>
				<li>Pay Day: <?php echo date("D F j, Y",$pay_day); ?></li>
				<li>Frequency:  Bi-Weekly</li>
				<li><b>Due Date: <?php echo date("D F j, Y",$due_date); ?></b></li>
			</ul>
			
			
		
		<div style="float:left;clear:both;">
			-------------------MONTHLY TESTING---------------------
			
			<!-- ex 1 -->
			<?php
/*    ------------------------------------------------MONTHLY TESTING ------------------------------------------------     */
/*    ----------------------------------------------------------------------------------------------------------------     */
				//----------------------------
				$fund_day	= 1528675200;	// June 11, 2018  	
				$pay_day	= 1528070400;	// June 4, 2018  	
				$due_date = $Paydate_Calculator->Calculate_Due_Date($fund_day,$holiday_array,"monthly",$pay_day,true);
				//
				// monthly
				// Answer: July 3, 2018
				// ---------------------------
			?>
		
			<ul style="float:left">
				<li>Fund Day: <?php echo date("D F j, Y",$fund_day); ?></li>
				<li>Pay Day: <?php echo date("D F j, Y",$pay_day); ?></li>
				<li>Frequency:  Monthly</li>
				<li><b>Due Date: <?php echo date("D F j, Y",$due_date); ?></b></li>
			</ul>
			
			<!-- ex 2 -->			
			<?php
				//----------------------------
				$fund_day 	= 1528070400;	// June 4, 2018  	
				$pay_day	= 1528675200;	// June 11, 2018  
				$due_date = $Paydate_Calculator->Calculate_Due_Date($fund_day,$holiday_array,"monthly",$pay_day,true);
				//
				// monthly
				// Answer: July 13, 2018
				// ---------------------------
			?>
	
			<ul style="float:left">
				<li>Fund Day: <?php echo date("D F j, Y",$fund_day); ?></li>
				<li>Pay Day: <?php echo date("D F j, Y",$pay_day); ?></li>
				<li>Frequency:  Monthly</li>
				<li><b>Due Date: <?php echo date("D F j, Y",$due_date); ?></b></li>
			</ul>
			
			
		
	</body>
</html>