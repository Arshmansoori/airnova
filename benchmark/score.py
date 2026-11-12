"""Score pre-matched AQI estimates against reference observations."""

import argparse
import csv
from datetime import datetime, timedelta
import math
from pathlib import Path
import statistics

COLUMNS = ("location_id", "valid_time_utc", "estimated_aqi", "reference_aqi")


def score(path):
    errors = {}
    with path.open(encoding="utf-8-sig", newline="") as source:
        rows = csv.reader(source, strict=True)
        if tuple(next(rows, ())) != COLUMNS:
            raise ValueError(f"expected CSV header: {','.join(COLUMNS)}")

        for row in rows:
            line = rows.line_num
            try:
                location, valid_time, estimated, reference = row
                location = location.strip()
                if not location:
                    raise ValueError("location_id is empty")
                timestamp = datetime.fromisoformat(valid_time)
                if "T" not in valid_time or timestamp.utcoffset() != timedelta(0):
                    raise ValueError("valid_time_utc must be an ISO 8601 UTC datetime")
                estimated_aqi, reference_aqi = float(estimated), float(reference)
                if not all(math.isfinite(x) and 0 <= x <= 500
                           for x in (estimated_aqi, reference_aqi)):
                    raise ValueError("AQI values must be finite and in [0, 500]")
                key = (location, timestamp)
                if key in errors:
                    raise ValueError("duplicate location and valid time")
                errors[key] = abs(estimated_aqi - reference_aqi)
            except ValueError as exc:
                raise ValueError(f"line {line}: {exc}") from exc

    if not errors:
        raise ValueError("CSV contains no matched pairs")
    return len(errors), statistics.mean(errors.values())


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("csv_file", type=Path, help="CSV of matched AQI pairs")
    args = parser.parse_args()
    try:
        count, mae = score(args.csv_file)
    except (OSError, ValueError, csv.Error) as exc:
        parser.exit(2, f"error: {exc}\n")
    print(f"Matched pairs: {count}")
    print(f"MAE: {mae:.2f} AQI points")


if __name__ == "__main__":
    main()
