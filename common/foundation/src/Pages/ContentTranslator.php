<?php

namespace Common\Pages;

interface ContentTranslator
{
    public function translateCustomPage(CustomPage $page): CustomPage;

    public function translateLandingData(array $data): array;

    public function translateAuthLines(array $lines): array;
}
