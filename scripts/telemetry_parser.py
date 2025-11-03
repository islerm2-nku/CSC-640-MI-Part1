#!/usr/bin/env python3
from irsdk import IBT, CustomYamlSafeLoader, YAML_TRANSLATER, YAML_CODE_PAGE
import json
import re
from yaml.reader import Reader as YamlReader
from yaml.cyaml import CSafeLoader as YamlSafeLoader
import yaml
import sys
import json
import debugpy
from add_telemetry import add_telemetry

# Enable debugging only when running in Docker
#debugpy.listen(("0.0.0.0", 5678))
#debugpy.wait_for_client()  # Pause execution until debugger attaches

def parse_telemetry(file_path, attributes):
    try:
        # Initialize irsdk with the .ibt file
        ir = IBT()
        ir.open(ibt_file=file_path)
        telemetry_data = to_json(ir, attributes)
        upserted_data = add_telemetry(telemetry_data)
        ir.close()
        return {"uploaded": True, "session_id": upserted_data["session_info"]["session_id"]}
    except Exception as e:
        return json.dumps({
            "error": str(e)
        })

def to_json(self, attributes):
    """Convert all telemetry data to a JSON-serializable dictionary."""
    if not self._header:
        return None
    
    result = {
        'file_name': self.file_name,
        'session_info': get_all_session_info(self),
        'telemetry': {}
    }
    #always include lap data, this is requried to get starting and ending frame for a lap
    if "Lap" not in attributes:
        attributes.append("Lap")
    
    # Add all variables for all records
    for var_name in attributes:
        result['telemetry'][var_name] = self.get_all(var_name)
    return result

def get_all_session_info(self):
    """Extract all session info sections as a dictionary."""
    if not self._header:
        return None
    
    session_info = {}
    # Common session info sections
    for section in ['SessionInfo', 'DriverInfo', 'WeekendInfo',"WeatherInfo", "SplitTimeInfo"]:
    #for section in ['SessionInfo']:
        data = get_session_info_section(self, section)
        if data:
            session_info[section] = data
    
    return session_info

def get_session_info_section(self, section_name):
    """Parse a specific session info section."""
    data_binary = get_session_info_binary(self, section_name)
    if not data_binary:
        return None
    
    yaml_src = re.sub(YamlReader.NON_PRINTABLE, '', 
                        data_binary.translate(YAML_TRANSLATER).rstrip(b'\x00').decode(YAML_CODE_PAGE))
    
    try:
        result = yaml.load(yaml_src, Loader=CustomYamlSafeLoader)
        return result.get(section_name) if result else None
    except Exception as e:
        print(f'Error parsing YAML for section {section_name}: {e}')
        return None
    
def get_session_info_binary(ibt, key):
    """Get binary session info data for a given key."""
    if not ibt._header:
        return None
    
    start = ibt._header.session_info_offset
    end = start + ibt._header.session_info_len
    # search section by key
    match_start = re.compile(('\n%s:\n' % key).encode(YAML_CODE_PAGE)).search(ibt._shared_mem, start, end)
    if not match_start:
        return None
    match_end = re.compile(b'\n\n').search(ibt._shared_mem, match_start.start() + 1, end)
    if not match_end:
        return None
    return ibt._shared_mem[match_start.start() + 1 : match_end.start()]


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "File path argument required"}))
        sys.exit(1)
    
    # Get attributes from second argument, default to empty list
    attributes = json.loads(sys.argv[2]) if len(sys.argv) > 2 else []
    
    result = parse_telemetry(sys.argv[1], attributes)
    print(json.dumps(result))