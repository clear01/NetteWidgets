<?php
namespace Clear01\Widgets\Nette;

use Clear01\Widgets\IUserIdentityAccessor;
use Nette\Security\User;

class NetteUserIdentityAccessor implements IUserIdentityAccessor
{
	/** @var  User */
	protected $user;

	/**
	 * NetteUserIdentityAccessor constructor.
	 * @param User $user
	 */
	public function __construct(User $user)
	{
		$this->user = $user;
	}

	public function getUserId()
	{
		if(!$this->user->isLoggedIn()) {
			throw new \RuntimeException("No user is logged in at the moment.");
		}
		return $this->user->getId();
	}

}