import Box from "@mui/material/Box";
import Checkbox from "@mui/material/Checkbox";
import FormControlLabel from "@mui/material/FormControlLabel";
import Typography from "@mui/material/Typography";

interface FeatureDefaultsCheckboxesProps {
    allFeatures: string[];
    existingDefaults: string[];
    selectedDefaults: string[];
    onToggle: (_feature: string) => void;
}

export default function FeatureDefaultsCheckboxes({
    allFeatures,
    existingDefaults,
    selectedDefaults,
    onToggle,
}: FeatureDefaultsCheckboxesProps) {
    return (
        <Box sx={{ mb: 2 }}>
            <Typography variant="subtitle2" sx={{ mb: 1 }}>
                Feature Defaults
            </Typography>
            <Typography
                variant="caption"
                color="text.secondary"
                display="block"
                sx={{ mb: 1 }}
            >
                Select features this system should be the default for.
                Greyed-out features are already assigned to another system.
            </Typography>
            {allFeatures.map((feature) => {
                const takenByOther = existingDefaults.includes(feature);

                return (
                    <FormControlLabel
                        key={feature}
                        control={
                            <Checkbox
                                checked={selectedDefaults.includes(feature)}
                                onChange={() => {
                                    onToggle(feature);
                                }}
                                disabled={takenByOther}
                            />
                        }
                        label={feature}
                    />
                );
            })}
        </Box>
    );
}
