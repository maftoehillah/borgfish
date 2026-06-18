import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    marketplace_browse: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 20 },
        { duration: '1m', target: 50 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1200'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost';

export default function () {
  const indexRes = http.get(`${BASE_URL}/ikans`);
  check(indexRes, {
    'marketplace index status 200': (r) => r.status === 200,
  });

  // Optional: set LOT_ID via env to stress lot state polling endpoint.
  if (__ENV.LOT_ID) {
    const stateRes = http.get(`${BASE_URL}/ikans/${__ENV.LOT_ID}/state`);
    check(stateRes, {
      'lot state status 200': (r) => r.status === 200,
    });
  }

  sleep(1);
}
