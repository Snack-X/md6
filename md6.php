<?php
/**
 * 
 *  md6hash for PHP
 *
 *  Usage :
 *      $md6 = new md6hash;
 *      $data = "md6 FTW";
 *      $result = $md6->hex($data);
 *      echo $result; // 7bfaa624f661a683be2a3b2007493006a30a7845ee1670e499927861a8e74cce
 *
 */
class md6hash {
	private function to_word($input) {
		$output = array();

		for($i = 0 ; $i < count($input) ; $i += 8) {
			array_push($output, array(
				(($input[$i  ] << 24) | ($input[$i+1] << 16) | ($input[$i+2] << 8) | ($input[$i+3])),
				(($input[$i+4] << 24) | ($input[$i+5] << 16) | ($input[$i+6] << 8) | ($input[$i+7]))
			));
		}

		return $output;
	}

	private function from_word($input) {
		$output = array();

		for($i = 0 ; $i < count($input) ; $i += 1) {
			array_push($output, ($input[$i][0] >> 24) & 0xff);
			array_push($output, ($input[$i][0] >> 16) & 0xff);
			array_push($output, ($input[$i][0] >>  8) & 0xff);
			array_push($output, ($input[$i][0] >>  0) & 0xff);
			array_push($output, ($input[$i][1] >> 24) & 0xff);
			array_push($output, ($input[$i][1] >> 16) & 0xff);
			array_push($output, ($input[$i][1] >>  8) & 0xff);
			array_push($output, ($input[$i][1] >>  0) & 0xff);
		}

		return $output;
	}

	private function _xor($x, $y) {
		return array($x[0] ^ $y[0], $x[1] ^ $y[1]);
	}

	private function _and($x, $y) {
		return array($x[0] & $y[0], $x[1] & $y[1]);
	}
	private function _shl($x, $n) {
		$a = $x[0];
		$b = $x[1];

		if($n >= 32) {
			return array(($b << ($n - 32)) & 0xffffffff, 0x00000000);
		}
		else {
			return array((($a << $n) | ($b >> (32 - $n))) & 0xffffffff, ($b << $n) & 0xffffffff);
		}
	}

	private function _shr($x, $n) {
		$a = $x[0];
		$b = $x[1];

		if($n >= 32) {
			return array(0x00000000, $a >> ($n - 32));
		}
		else {
			return array(($a >> $n) & 0xffffffff, (($a << (32 - $n)) | ($b >> $n)) & 0xffffffff);
		}
	}

	private function crop($size, $hash, $right = false) {
		$length = floor(($size + 7) / 8);
		$remain = $size % 8;

		if($right) {
			$hash = array_slice($hash, count($hash) - $length);
		}
		else {
			$hash = array_slice($hash, 0, $length);
		}
		if($remain > 0) {
			$hash[$length - 1] &= (0xff << (8 - $remain)) & 0xff;
		}

		return $hash;
	}

	private function bytes($input) {
		$output = array();
		$length = strlen($input);

		for($i = 0 ; $i < $length ; $i++) {
			array_push($output, ord($input[$i]));
		}

		return $output;
	}

	private function f($N) {
		$S = array_merge(array(), $this->S0);
		$A = array_merge(array(), $N);

		for($j = 0, $i = $this->n ; $j < $this->r ; $j += 1, $i += 16) {
			for($s = 0 ; $s < 16 ; $s += 1) {
				$x = array_merge(array(), $S);
				$x = $this->_xor($x, $A[$i + $s - $this->t[5]]);
				$x = $this->_xor($x, $A[$i + $s - $this->t[0]]);
				$x = $this->_xor($x, $this->_and($A[$i + $s - $this->t[1]], $A[$i + $s - $this->t[2]]));
				$x = $this->_xor($x, $this->_and($A[$i + $s - $this->t[3]], $A[$i + $s - $this->t[4]]));
				$x = $this->_xor($x, $this->_shr($x, $this->rs[$s]));
				$A[$i + $s] = $this->_xor($x, $this->_shl($x, $this->ls[$s]));
			}

			$S = $this->_xor($this->_xor($this->_shl($S, 1), $this->_shr($S, 63)), $this->_and($S, $this->Sm));
		}
		return array_slice($A, count($A) - 16);
	}

	private function mid($B, $C, $i, $p, $z) {
		$U = array(
			(($this->ell & 0xff) << 24) | (($i / 0xffffffff) & 0xffffff),
			$i & 0xffffffff
		);

		$V = array(
			(($this->r & 0xfff) << 16) | (($this->L & 0xff) << 8) | (($z & 0xf) << 4) | (($p & 0xf000) >> 12),
			(($p & 0xfff) << 20) | (($this->k & 0xff) << 12) | (($this->d & 0xfff))
		);

		return $this->f(array_merge($this->Q, $this->K, array($U, $V), $C, $B));
	}

	private function par($M) {
		$P = 0;
		$B = array();
		$C = array();

		$z = count($M) > $this->b ? 0 : 1;

		while(count($M) < 1 || (count($M) % $this->b) > 0) {
			array_push($M, 0x00);
			$P += 8;
		}

		$M = $this->to_word($M);

		while(count($M) > 0) {
			array_push($B, array_slice($M, 0, ($this->b / 8)));
			$M = array_slice($M, $this->b / 8);
		}

		for($i = 0, $p = 0, $l = count($B) ; $i < $l ; $i += 1, $p = 0) {
			$p = ($i === (count($B) - 1)) ? $P : 0;
			$C = array_merge($C, $this->mid($B[$i], array(), $i, $p, $z));
		}

		return $this->from_word($C);
	}

	private function seq($M) {
		$P = 0;
		$B = array();
		$C = array(
			array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0),
			array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0),
			array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0),
			array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0), array(0x0, 0x0)
		);

		while(count($M) < 1 || (count($M) % ($this->b - $this->c)) > 0) {
			array_push($M, 0x00);
			$P += 8;
		}

		$M = $this->to_word($M);

		while(count($M) > 0) {
			array_push($B, array_slice($M, 0, (($this->b - $this->c) / 8)));
			$M = array_slice(($this->b - $this->c) / 8);
		}

		for($i = 0, $p = 0, $l = count($B) ; $i < $l ; $i += 1, $p = 0) {
			$p = ($i === (count($B) - 1)) ? $P : 0;
			$z = ($i === (count($B) - 1)) ? 1 : 0;
			$C = $this->mid($B[$i], $C, $i, $p, $z);
		}

		return $this->from_word($C);
	}

	private $b, $c, $n, $d, $K, $k, $r, $L, $ell, $S0, $Sm, $Q, $t, $rs, $ls;

	private function _hash($data, $size, $key, $levels) {
		$this->b = 512;
		$this->c = 128;

		$this->n = 89;

		$this->d = $size;
		$this->M = $data;

		$this->K = array_slice($key, 0, 64);
		$this->k = count($this->K);

		while(count($this->K) < 64) {
			array_push($this->K, 0x00);
		}

		$this->K = $this->to_word($this->K);

		$this->r = max(($this->k ? 80 : 0), (40 + ($this->d / 4)));

		$this->L = $levels;
		$this->ell = 0;

		$this->S0 = array(0x01234567, 0x89abcdef);
		$this->Sm = array(0x7311c281, 0x2425cfa0);

		$this->Q = array(
			array(0x7311c281, 0x2425cfa0), array(0x64322864, 0x34aac8e7), array(0xb60450e9, 0xef68b7c1),
			array(0xe8fb2390, 0x8d9f06f1), array(0xdd2e76cb, 0xa691e5bf), array(0x0cd0d63b, 0x2c30bc41),
			array(0x1f8ccf68, 0x23058f8a), array(0x54e5ed5b, 0x88e3775d), array(0x4ad12aae, 0x0a6d6031),
			array(0x3e7f16bb, 0x88222e0d), array(0x8af8671d, 0x3fb50c2c), array(0x995ad117, 0x8bd25c31),
			array(0xc878c1dd, 0x04c4b633), array(0x3b72066c, 0x7a1552ac), array(0x0d6f3522, 0x631effcb)
		);

		$this->t = array(17, 18, 21, 31, 67, 89);
		$this->rs = array(10,  5, 13, 10, 11, 12,  2,  7, 14, 15,  7, 13, 11, 7, 6, 12);
		$this->ls = array(11, 24,  9, 16, 15,  9, 27, 15,  6,  2, 29,  8, 15, 5, 31, 9);

		do {
			$this->ell += 1;

			$this->M = $this->ell > $this->L ? $this->seq($this->M) : $this->par($this->M);
		} while(count($this->M) !== $this->c);

		return $this->crop($this->d, $this->M, true);
	}

	private function _prehash($data, $size, $key, $levels) {
		$data = $this->bytes($data);
		$key = $this->bytes($key);

		if($size <= 0) $size = 1;
		if($size > 512) $size = 512;

		return $this->_hash($data, $size, $key, $levels);
	}

	public function hex($data = "", $size = 256, $key = "", $levels = 64) {
		$hash = $this->_prehash($data, $size, $key, $levels);
		$hex = "";
		foreach($hash as $v) $hex .= str_pad(dechex($v), 2, "0", STR_PAD_LEFT);
		return $hex;
	}

	public function raw($data = "", $size = 256, $key = "", $levels = 64) {
		$hash = $this->_prehash($data, $size, $key, $levels);
		$raw = "";
		foreach($hash as $v) $raw .= chr($v);
		return $raw;
	}
}