# API curl examples

This file contains example `curl` commands for each API endpoint in this project. Replace the placeholders:

- `<SESSION_ID>` — session id returned by upload / sessions endpoints


## 1) Upload telemetry (POST /api/telemetry/upload)

Upload a local `.ibt` file. `attributes` is optional and may be a JSON array string.

Note: this endpoint contains a filepath and assumes you are running curl from the root of this project

```bash
curl --request POST \
  --url http://localhost/api/telemetry/upload \
  --header 'content-type: multipart/form-data' \
  --form 'telemetry_file=@telemetry/porsche992rgt3_roadatlanta full_test1.ibt' \ 
  --form 'attributes[]=Lap' \
  --form 'attributes[]=RPM' \
  --form 'attributes[]=Speed' \
  --form 'attributes[]=LapDistPct' \
  --form 'attributes[]=FuelLevel' \
  --form 'attributes[]=RFpressure' \
  --form 'attributes[]=RRpressure' \
  --form 'attributes[]=LFpressure' \
  --form 'attributes[]=LRpressure' \
  --form 'attributes[]=PlayerIncidents' \
  --form 'attributes[]=OnPitRoad'
```

If the upload succeeds you'll get a JSON response with parsed attributes and (if stored) a session id.

## 2) List all sessions (GET /api/sessions)

```bash
curl --request GET \
  --url http://localhost/api/sessions
```

## 3) Get a single session (GET /api/sessions/{id})

```bash
curl --request GET \
  --url http://localhost/api/sessions/$SESSION_ID
```

## 4) Get lap list / counts (GET /api/sessions/{id}/laps)

Returns detected laps (start/end indices) and incident info per lap.

```bash
curl --request GET \
  --url http://localhost/api/sessions/$SESSION_ID/laps
```

## 5) Get attribute data for a lap (GET /api/sessions/{id}/laps/{lapNumber})

Supports multiple attributes via comma-separated list or repeated `attribute` params.

- Comma-separated:

```bash
curl --request GET \
  --url 'http://localhost/api/sessions/'$SESSION_ID'/laps/7?attribute=RPM'
```

- Repeated params:

```bash
curl --request GET \
  --url 'http://localhost/api/sessions/'$SESSION_ID'/laps/7?attribute=RPM&attribute=Speed'
```

Response contains a map of attribute => { frame_index: value, ... } for the lap frame range.

## 6) Get attribute averages for a lap (GET /api/sessions/{id}/laps/{lapNumber}/averages)

Returns average/min/max/sample_count for each attribute. Same parameter format as above.

```bash
curl --request GET \
  --url 'http://localhost/api/sessions/'$SESSION_ID'/laps/7/averages?attribute=RFPressure%2CLFPressure%2CRRPressure%2CLRPressure'
```

## 7) Delete attribute data for a lap (DELETE /api/sessions/{id}/laps/{lapNumber})

- Delete a specific attribute or comma-separated attributes for the lap:

- Delete ALL attributes for the lap (no `attribute` param):

```bash
curl --request DELETE \
  --url 'http://localhost/api/sessions/'$SESSION_ID'/laps/7'
```

This removes stored frames for the specified lap range for each attribute (or for all attributes if none specified) and updates the stored JSON.

## 8) Delete a full session (DELETE /api/sessions/{id})

Deletes session row, weather, driver and attribute_values for the session.

```bash
curl --request DELETE \
  --url http://localhost/api/sessions/$SESSION_ID
```

---


