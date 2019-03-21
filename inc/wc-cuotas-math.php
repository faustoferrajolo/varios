<?php

class WC_Cuotas_Math {

	private $efective_interest;
	private $nominal_interest;
	private $num_installments;

	public $factor = null;
	public $installment = 0;

	public function setInterest( $cft ) {
		$this->nominal_interest = floatval( $cft ) / 100;
	}

	public function setInstallments( $numCuotas ) {
		$this->num_installments = $numCuotas;
	}

	public function calc( $amount = 1000 ) {
		$this->efective_interest = pow( 1 + $this->nominal_interest, 1/12 ) - 1;
		$installment = $this->pmt( $this->efective_interest, $this->num_installments, $amount );
		$total = $installment * $this->num_installments;
		$this->factor = $total / $amount;
		$this->installment = round($installment,2);
	}

	private function pmt($i, $n, $p)
	{
		return -$i * $p * pow((1 + $i), $n) / (1 - pow((1 + $i), $n));
	}

}
