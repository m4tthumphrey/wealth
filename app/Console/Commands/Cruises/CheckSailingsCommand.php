<?php

namespace App\Console\Commands\Cruises;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckSailingsCommand extends Command
{
    protected $name = 'cruises:check-sailings';

    private const ENDPOINT = 'https://www.celebritycruises.com/cruises/graph';

    private const NOTIFY_EMAIL = 'cruises@m4tt.co';

    private const CACHE_KEY = 'cruises:sailings:summary';

    private const QUERY = <<<'GRAPHQL'
        query CruisesSearchResults($filters: String, $qualifiers: String, $sort: CruiseSearchSort, $pagination: CruiseSearchPagination, $nlSearch: String) {
          cruiseSearch(
            filters: $filters
            qualifiers: $qualifiers
            sort: $sort
            pagination: $pagination
            nlSearch: $nlSearch
          ) {
            results {
              cruises {
                id
                productViewLink
                lowestPriceSailing {
                  bookingLink
                  id
                  lowestStateroomClassPrice {
                    price {
                      value
                      currency {
                        code
                      }
                    }
                    stateroomClass {
                      id
                      content {
                        code
                      }
                    }
                  }
                  sailDate
                  startDate
                  endDate
                  taxesAndFees {
                    value
                  }
                  taxesAndFeesIncluded
                }
                masterSailing {
                  itinerary {
                    name
                    code
                    sailingNights
                    departurePort {
                      code
                      name
                    }
                    ship {
                      code
                      name
                    }
                  }
                }
              }
              total
            }
          }
        }
        GRAPHQL;

    public function handle(): void
    {
        $client = new Client;

        try {
            $response = $client->post(self::ENDPOINT, [
                'headers' => [
                    'accept' => 'application/json',
                    'accept-language' => 'en-GB,en-US;q=0.9,en;q=0.8',
                    'apollographql-client-name' => 'rcg-cruise-search-client',
                    'apollographql-query-name' => 'GetSearchResults',
                    'brand' => 'C',
                    'cache-control' => 'no-cache',
                    'content-type' => 'application/json',
                    'country' => 'USA',
                    'currency' => 'USD',
                    'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
                ],
                'json' => [
                    'query' => self::QUERY,
                    'variables' => [
                        'filters' => 'departurePort:STH|startDate:2028-07-01~2028-07-31,2028-08-01~2028-08-31',
                        'sort' => ['by' => 'RECOMMENDED'],
                        'currency' => 'USD',
                        'pagination' => ['count' => 10, 'skip' => 0],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->error("Request failed: {$e->getMessage()}");
            Log::error('cruises:check-sailings request failed', ['exception' => $e]);

            return;
        }

        $cruises = json_decode((string) $response->getBody(), true)['data']['cruiseSearch']['results']['cruises'] ?? [];

        $current = $this->summarise($cruises);
        $previous = Cache::get(self::CACHE_KEY);

        Cache::forever(self::CACHE_KEY, $current);

        if ($previous === null) {
            $this->info('Baseline stored ('.count($current).' sailings), nothing to compare yet.');

            return;
        }

        $diff = $this->diff($previous, $current);

        if ($diff === []) {
            $this->info('No change.');

            return;
        }

        $this->info('Change detected, sending email.');

        Mail::raw(implode("\n", $diff), function ($message) {
            $message->to(self::NOTIFY_EMAIL)
                ->subject('Celebrity Cruises: sailing/price change detected');
        });
    }

    private function summarise(array $cruises): array
    {
        $summary = [];

        foreach ($cruises as $cruise) {
            $price = Arr::get($cruise, 'lowestPriceSailing.lowestStateroomClassPrice.price');
            $ship = Arr::get($cruise, 'masterSailing.itinerary.ship.name');
            $nights = Arr::get($cruise, 'masterSailing.itinerary.sailingNights');
            $sailDate = Arr::get($cruise, 'lowestPriceSailing.sailDate');

            $summary[$cruise['id']] = sprintf(
                '%s | %s nights | sails %s | %s %s',
                $ship ?? 'Unknown ship',
                $nights ?? '?',
                $sailDate ?? '?',
                $price['currency']['code'] ?? '?',
                $price['value'] ?? '?'
            );
        }

        return $summary;
    }

    private function diff(array $previous, array $current): array
    {
        $lines = [];

        foreach ($current as $id => $line) {
            if (! array_key_exists($id, $previous)) {
                $lines[] = "NEW SAILING: {$line}";
            } elseif ($previous[$id] !== $line) {
                $lines[] = "CHANGED: {$previous[$id]}  ->  {$line}";
            }
        }

        foreach ($previous as $id => $line) {
            if (! array_key_exists($id, $current)) {
                $lines[] = "NO LONGER LISTED: {$line}";
            }
        }

        return $lines;
    }
}
