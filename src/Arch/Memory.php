<?php 

namespace Parapente\Synacor\Arch;

class Memory
{
	private array $mem;

	public function __construct(array $state = [])
	{
		$this->mem = $state;
	}

	private function checkAddress(int $address)
	{
		if ($address < 0 || $address > 32767) {
			throw new \Exception("Invalid memory address: $address");
		}
	}

	public function getAddr(int $address): int
	{
		$this->checkAddress($address);

		return ($this->mem[2 * $address] ?? 0) + ($this->mem[2 * $address + 1] ?? 0) * 256;
	}

	public function setAddr(int $address, int $value): void
	{
		$this->checkAddress($address);

		if ($value < 0 || $value > 65535) {
			throw new \Exception("Invalid value $value. Value must be unsigned 16-bit");
		}

		$byte2 = intval(floor($value / 256));
		$byte1 = $value % 256;

		$this->mem[2 * $address] = $byte1;
		$this->mem[2 * $address + 1] = $byte2;
	}
}