<?php

/*

Laravel HashSlug: Package providing a trait to use Hashids on a model
Copyright (C) 2017  Balázs Dura-Kovács

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

namespace Balping\HashSlug;

trait HasHashSlug {
	private $slug = null;

	private static $hashIds = null;

	private static function getHashids(){
		if (is_null(static::$hashIds)){

			$minSlugLength = 5;
			if(isset(static::$minSlugLength)) {
				$minSlugLength = static::$minSlugLength;
			}

			if(isset(static::$modelSalt)) {
				$modelSalt = static::$modelSalt;
			}else{
				$modelSalt = get_called_class();
			}

			if(isset(static::$alphabet)) {
				$alphabet = static::$alphabet;
			}else{
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			}

			$salt = config('hashslug.appsalt', env('APP_KEY')) . $modelSalt;
			
			// This is impotant!
			// Don't use a weak hash, otherwise
			// your app key can be exposed
			// http://carnage.github.io/2015/08/cryptanalysis-of-hashids
			$salt = hash('sha256', $salt);

			static::$hashIds = new \Hashids\Hashids($salt, $minSlugLength, $alphabet);
		}

		return static::$hashIds;
	}

	public function slug(){
		if (is_null($this->slug)){
			$hashids = $this->getHashids();

			$this->slug = $hashids->encode($this->id);
		}

		return $this->slug;
	}

	public function getRouteKey() {
		return $this->slug();
	}

	private static function decodeSlug($slug){
		$hashids = static::getHashids();

		$id = $hashids->decode($slug)[0];

		return $id;
	}

	public static function findBySlugOrFail($slug){
		$id = static::decodeSlug($slug);

		return static::findOrFail($id);
	}

	public static function findBySlug($slug){
		$id = static::decodeSlug($slug);

		return static::find($id);
	}
}
