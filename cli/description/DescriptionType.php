<?php

namespace cli\description;

interface DescriptionType{
	public const TYPE_NORMAL = "raw";//github
	public const TYPE_PHAR = "phar";//github in phar
	public const TYPE_LIBRARY = "library";
}