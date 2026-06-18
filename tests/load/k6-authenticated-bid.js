import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const bidRequestOk = new Rate('bid_request_ok');

export const options = {
  scenarios: {
    bid_pressure: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 10 },
        { duration: '1m', target: 30 },
        { duration: '30s', target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1500'],
    bid_request_ok: ['rate>0.95'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';
const LOT_IDS = (__ENV.LOT_IDS || '').split(',').map((v) => v.trim()).filter(Boolean);
const AUTH_COOKIE = __ENV.AUTH_COOKIE || '';
const CSRF_TOKEN = __ENV.CSRF_TOKEN || '';
const RETURN_URL = __ENV.RETURN_URL || '/ikans';
const BID_AMOUNT_BASE = Number.parseInt(__ENV.BID_AMOUNT_BASE || '100000', 10);

if (LOT_IDS.length === 0) {
  throw new Error('LOT_IDS wajib diisi, contoh: -e LOT_IDS=101,102');
}

if (!AUTH_COOKIE) {
  throw new Error('AUTH_COOKIE wajib diisi, contoh: -e AUTH_COOKIE="laravel_session=..."');
}

if (!CSRF_TOKEN) {
  throw new Error('CSRF_TOKEN wajib diisi dari sesi login bidder staging.');
}

function pickLotId() {
  const index = (__VU + __ITER) % LOT_IDS.length;
  return LOT_IDS[index];
}

export default function () {
  const lotId = pickLotId();
  const bidAmount = BID_AMOUNT_BASE + ((__ITER % 5) * 1000);

  const formBody = {
    _token: CSRF_TOKEN,
    jumlah_bid: String(bidAmount),
    return_url: `${BASE_URL}${RETURN_URL}`,
  };

  const bidRes = http.post(
    `${BASE_URL}/bid/${lotId}`,
    formBody,
    {
      headers: {
        Cookie: AUTH_COOKIE,
      },
      redirects: 0,
    }
  );

  const okStatus = bidRes.status === 302 || bidRes.status === 429;
  bidRequestOk.add(okStatus);

  check(bidRes, {
    'bid response accepted status': () => okStatus,
  });

  const stateRes = http.get(`${BASE_URL}/ikans/${lotId}/state`);
  check(stateRes, {
    'lot state poll status 200': (r) => r.status === 200,
  });

  sleep(1);
}
